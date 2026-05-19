## Context

Rex's brief asks for a CRM contacts module that runs the same business rules through both HTTP and CLI, validates phone/email strictly (AU/NZ E164 only), and is consumed by a co-located React SPA. The codebase is a fresh Laravel 13 + PHP 8.5 starter kit with PHPStan max, Rector, Pest, ArchTest, and `Model::preventLazyLoading()` already wired up.

The starter kit's default layout puts everything under `app/`. To satisfy the brief's "deployable into a real product codebase" bar and to keep business rules out of HTTP plumbing, we introduce a `src/Domain/` layer for any custom business logic, with one bounded context per aggregate. `Contact` is the first (and for the exercise, only) aggregate.

## Goals / Non-Goals

**Goals:**

- Single source of truth for the five operations (upsert / delete / search / read / call) — both `routes/api.php` and `app/Console/Commands/Contact/*` dispatch the same `Domain\Contact\Actions\*` classes.
- Phone and email validity enforced at the value-object boundary, so a `Contact` instance cannot exist with an invalid number or address regardless of entry point.
- Informative API errors (per brief: third-party consumers). Field-level errors via Laravel's `422` envelope; domain rejections (invalid VO construction, AU/NZ region check) surface as `422` with a `code` discriminator the FE can branch on.
- Tests prove the rules, not the wiring — value-object unit tests for every validation branch, feature tests against the HTTP and console kernels.

**Non-Goals:**

- Authentication, authorisation, multi-tenancy.
- Real telephony — only the `FakeTelephonyGateway` ships; the interface is the seam.
- Soft-delete, audit log, contact merging, fuzzy / phonetic search, full-text search.
- `libphonenumber` integration (see Decisions for trade-off).
- Pagination beyond a hard `LIMIT` on list/search (exercise scale).

## Decisions

### D1. DDD-lite under `src/Domain/`, per-aggregate context

`composer.json` adds `"Domain\\": "src/Domain/"` to `autoload.psr-4`. The Contact aggregate lives at `src/Domain/Contact/`:

```
src/Domain/Contact/
├── Actions/
│   ├── UpsertContact.php        # write: create or update by id
│   ├── DeleteContact.php        # write
│   ├── GetContact.php           # read
│   ├── ListContacts.php         # read: paginated list
│   ├── SearchContacts.php       # read: by name | phone | email-domain
│   └── PlaceCallToContact.php   # external side-effect via TelephonyGateway
├── DataTransferObjects/
│   ├── ContactData.php          # input DTO with toModelAttributes()
│   ├── ContactSearchCriteria.php
│   └── CallOutcome.php          # output DTO from PlaceCallToContact
└── ValueObjects/
    ├── PhoneNumber.php          # E164 + AU/NZ region
    └── EmailAddress.php
```

Eloquent model stays in `app/Models/Contact.php` (Laravel discovery, factory paths, IDE familiarity). Actions own all query chains — controllers and commands never touch `Contact::query()` directly.

**Alternative considered:** put the model under `src/Domain/Contact/Models/`. Rejected — factories, migrations, and Laravel's model discovery all assume `app/Models/`; fighting that for one model adds friction without payoff. The Domain layer owns the *behaviour*; the model is a record-mapper.

### D2. `Contact` has many phones and emails (separate tables)

```
contacts          (id, name, created_at, updated_at)
contact_phones    (id, contact_id FK, e164 UNIQUE, created_at, updated_at)
contact_emails    (id, contact_id FK, address, created_at, updated_at)
                                            ↑ stored lowercase, UNIQUE
```

Indexes: `contact_phones.e164` UNIQUE, `contact_emails.address` UNIQUE, `contact_emails.address_domain` (generated column) for email-domain search, `contacts.name` for prefix search.

**Alternative considered:** JSON columns for phones/emails on `contacts`. Rejected — kills index-backed search-by-phone (the brief's explicit search axis) and forces `LIKE '%...%'` over JSON. Separate tables are the boring correct choice.

### D3. Value objects validate at construction; model casts to/from them

`PhoneNumber::__construct(string $e164)` throws `InvalidPhoneNumberException` if:

- Doesn't match `/^\+(61|64)\d{8,10}$/` (AU starts `+61`, NZ starts `+64`; digit-count window covers AU mobile/landline and NZ mobile/landline).
- Contains anything other than `+` and digits.

`EmailAddress::__construct(string $address)` lowercases, then validates via `filter_var(..., FILTER_VALIDATE_EMAIL)` plus a length cap of 254 (RFC 5321). Stores the domain part as a derived property for the search index.

Both VOs are `final readonly` with a `__toString()` for casting. `ContactPhone` and `ContactEmail` models use a custom cast (`PhoneNumberCast`, `EmailAddressCast`) so any read returns the VO and any write rejects raw strings at the boundary.

**Alternative considered:** validate only in Form Requests. Rejected — CLI entry would then have a parallel validation path, and a queued job re-hydrating a DTO from JSON could re-create an invalid VO. VO-at-the-boundary closes both.

### D4. DTO is the mass-assignment allowlist; model uses `$guarded = []`

Per the user's DDD discipline, `Contact` and its children declare `protected $guarded = []` (no `$fillable`). `ContactData::toModelAttributes()` is the hand-curated allowlist:

```php
final readonly class ContactData
{
    /** @param list<PhoneNumber> $phones @param list<EmailAddress> $emails */
    public function __construct(public string $name, public array $phones, public array $emails) {}

    /** @return array{name: string} */
    public function toModelAttributes(): array
    {
        return ['name' => $this->name];
    }
}
```

`UpsertContact` consumes the DTO; child rows (`ContactPhone`, `ContactEmail`) are written via their owning aggregates' actions if we ever split them out. For this exercise, both are children of `Contact` and live under `Domain\Contact\Actions\Upsert*` — they never get queried from outside the Contact context.

### D5. Search: three orthogonal axes, one action, indexed lookups

`SearchContacts::execute(ContactSearchCriteria)` returns a `Collection<int, Contact>`. The criteria DTO carries `?string $name`, `?PhoneNumber $phone`, `?string $emailDomain`; the action issues separate, index-backed queries depending on which fields are set, then unions by contact id. No `whereHas` with `LIKE` over child columns — instead `whereIn('id', $matchedIds)` on `contacts`, where `$matchedIds` comes from a `pluck()` on the child table (per advanced-queries.md rule).

Name search is case-insensitive prefix: `WHERE name LIKE 'jane%'`. Documented in the README as the simplest indexable choice for the exercise; flagged as the first thing to swap for Scout / Meilisearch / pg trgm if requirements grow.

### D6. Call: `TelephonyGateway` interface + `FakeTelephonyGateway`

```php
interface TelephonyGateway
{
    public function call(PhoneNumber $to): CallOutcome;
}

final readonly class CallOutcome
{
    public function __construct(
        public CallStatus $status,         // enum: connected, no_answer, busy, failed, invalid_number
        public ?int $durationSeconds,      // null unless connected
        public ?string $providerMessage,
    ) {}
}
```

`FakeTelephonyGateway` is bound in `AppServiceProvider` with a deterministic-by-default seam (`Random::fromSeed(...)`) so tests get stable outcomes without `Random::fake()` ceremony. `PlaceCallToContact` picks the contact's *primary* phone (first `contact_phones` row by `created_at`), calls the gateway, and returns the `CallOutcome`. It does *not* persist a call log for this exercise — flagged in the README as the obvious next aggregate (`Domain\CallLog\`).

### D7. HTTP API shape

Versioned under `/api/v1/contacts`:

| Verb   | Path                       | Action               |
|--------|----------------------------|----------------------|
| GET    | `/contacts`                | `ListContacts`       |
| GET    | `/contacts/search`         | `SearchContacts`     |
| GET    | `/contacts/{contact}`      | `GetContact`         |
| POST   | `/contacts`                | `UpsertContact` (no id) |
| PUT    | `/contacts/{contact}`      | `UpsertContact` (with id) |
| DELETE | `/contacts/{contact}`      | `DeleteContact`      |
| POST   | `/contacts/{contact}/call` | `PlaceCallToContact` |

Implicit route model binding on `{contact}`. `ContactResource` shapes the JSON envelope. Form Requests handle field validation; domain VO exceptions are mapped to `422` with `code` in `bootstrap/app.php`'s exception handler.

Throttle on the call endpoint (`throttle:10,1` per route group) since it represents an outbound external call in production.

### D8. CLI shape

`php artisan contact:upsert --name=... --phone=... --email=...` (repeatable `--phone` / `--email`), `contact:delete {id}`, `contact:search [--name=] [--phone=] [--email-domain=]`, `contact:show {id}`, `contact:call {id}`. Each command builds the same DTO and dispatches the same action — no parallel logic.

### D9. React SPA

Vite is already wired in the starter. Add a `resources/js/app.tsx` entry (the starter ships Blade-only; React is the brief's choice). Routes via `react-router` (already in starter, verify on first install): `/contacts`, `/contacts/new`, `/contacts/:id`, `/contacts/:id/edit`. Server state via TanStack Query. Form via `react-hook-form` with a zod schema mirroring the backend rules. Blade root view renders the SPA shell with the CSRF token.

### D10. Testing strategy

- **Unit:** every `PhoneNumber` / `EmailAddress` branch (valid AU mobile, valid NZ landline, wrong country code, wrong length, non-digit chars, missing `+`, lowercased email, invalid TLD, empty domain). Each action gets a unit test that constructs the DTO directly and asserts the side effects.
- **Feature (HTTP):** one Pest file per endpoint covering happy path, validation 422, not-found 404, the five call outcomes.
- **Feature (CLI):** one Pest file per command using `Artisan::call(...)` and `expectsOutput`.
- **Arch:** `Domain\\` namespace contains only Actions, DTOs, ValueObjects, and exceptions; no controllers, no models, no facades.

`LazilyRefreshDatabase`, factories with named states (`->withPhone(...)`, `->withEmail(...)`), `recycle($contact)` for child factories. Use `Http::preventStrayRequests()` even though the fake gateway is in-process — guards future drift.

## Risks / Trade-offs

- **Hand-rolled E164 regex misses edge cases.** AU mobile is `+614xxxxxxxx`, AU landline `+61[2378]xxxxxxxx`, NZ mobile `+642x…`, NZ landline `+643/4/6/7/9…`. A blanket `+(61|64)\d{8,10}` accepts shapes that libphonenumber would reject (e.g. `+61` followed by an area code that doesn't exist). **Mitigation:** documented in README as a known concession; the seam (`PhoneNumber::__construct`) means swapping in libphonenumber later is a single-file change.
- **Search is `LIKE`-based, not full-text.** Adequate at exercise scale; degrades at >100k contacts. **Mitigation:** README flags it; `SearchContacts` action is the only place that needs to change.
- **No optimistic locking on `UpsertContact`.** Two simultaneous PUTs last-write-wins. **Mitigation:** out of scope for the exercise; flagged.
- **`FakeTelephonyGateway` randomness inside a request could surprise reviewers.** **Mitigation:** seeded by default, override available in tests; `CallOutcome` is fully deterministic given the seed.
- **`src/Domain/` not the Laravel convention.** Reviewers used to vanilla Laravel may find it surprising. **Mitigation:** README explains the decision and the per-aggregate rule; ArchTest pins it.

## Migration Plan

This is greenfield — no production data, no downtime concern.

1. `composer.json` autoload update + `composer dump-autoload`.
2. Migrations land together (`contacts`, `contact_phones`, `contact_emails`).
3. Routes / commands wired last so the SPA build doesn't fail before the API exists.
4. PR squashed to a single feature commit on top of `68f3dcc` (initial commit), per the brief.

Rollback: revert the PR. No DB to migrate down because this is the first feature.

## Open Questions

- **OQ1.** Should `UpsertContact` upsert child rows by E164 / address (treat them as natural keys) or replace the full child set on each PUT? Leaning toward "replace on PUT, add on POST" for predictable API semantics — confirm in spec.
- **OQ2.** Should `PlaceCallToContact` accept a specific phone id, or always pick the primary? Brief doesn't say. Leaning toward primary-by-default with an optional `phone_id` body param for the multi-phone case.
- **OQ3.** Do we need a hard search-result cap (`LIMIT 100`?) or paginate? Leaning toward a hard cap for the exercise (simpler FE), paginate noted as follow-up.
