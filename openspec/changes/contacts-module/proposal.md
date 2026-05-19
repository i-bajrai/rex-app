## Why

The Rex technical exercise asks for a contacts module inside a Laravel modular monolith that is reachable from both an HTTP API and an Artisan CLI, validated strictly (E164, AU/NZ only), and consumed by a React SPA in the same repo. The brief explicitly calls out that business rules need to run identically whether the entry point is HTTP, CLI, or a queued job — which is the textbook case for pushing logic out of controllers and into a domain layer.

We therefore bootstrap the module as the first vertical slice of a DDD-lite layout under `src/Domain/`, so the same module can absorb future CRM aggregates (companies, deals, activities) without reshaping the codebase later.

## What Changes

- Add `Domain\\` PSR-4 mapping to `src/` in `composer.json`; dump autoload.
- Create the `Contact` aggregate in `src/Domain/Contact/` with `Actions/`, `DataTransferObjects/`, `ValueObjects/` sub-namespaces.
- Introduce `PhoneNumber` and `EmailAddress` value objects enforcing E164 + AU/NZ region (`+61` / `+64`) and RFC-compliant email parsing — invalid input throws domain exceptions, never reaches the model.
- `Contact` model gets `$guarded = []` (no `$fillable`) and is only ever written via Domain actions taking a `ContactData` DTO with a hand-curated `toModelAttributes()`.
- Read actions live in the same domain (`ListContacts`, `SearchContacts`, `GetContact`) — controllers/commands never build Eloquent query chains directly.
- HTTP layer: versioned `routes/api.php` (`/api/v1/contacts`) exposing upsert / delete / search / show / call; thin controllers that dispatch to actions and shape responses via API Resources; Form Requests for input validation; consistent JSON error envelope so third-party API consumers get informative messages (brief requirement).
- CLI layer: `php artisan contact:*` commands (`upsert`, `delete`, `search`, `show`, `call`) — thin wrappers that build the same DTO and dispatch the same action.
- "Call" is a `PlaceCallToContact` action that delegates to a `TelephonyGateway` interface bound to a `FakeTelephonyGateway` in non-prod; the fake returns one of `connected | no-answer | busy | failed | invalid-number` so the response shape covers the realistic outcome space.
- React SPA in `resources/js/` (Vite-bundled) with routes for list, search, detail, upsert, and the call action — uses TanStack Query for server state and the existing Blade shell for the entry HTML.
- Pest feature tests for every API route, unit tests for value objects and actions, an `ArchTest` rule pinning `Domain\\` to actions/DTOs/value objects only.

Non-goals (called out so they don't sneak into scope):

- Authentication / multi-tenancy — out of scope for the exercise.
- Real telephony integration — the gateway interface is the boundary; only the fake ships.
- Soft-deletes, audit log, contact merging, deduping heuristics — future iterations.

## Capabilities

### New Capabilities

- `contacts`: stores and retrieves contacts (name + many phones + many emails) with strict value-object validation; supports upsert, delete, search by name / phone / email-domain, read, and placing a mocked outbound call. Single backend capability because all five operations sit on the same aggregate and share the same DTOs and validators — splitting `contact-call` out would fragment the spec without clarifying the contract. The capability also pins the consistent JSON error envelope (third-party-friendly per the brief) and asserts that HTTP, CLI, and any future queued/scheduled jobs route through a single `Domain\Contact\Actions\*` seam.
- `contacts-spa`: the React SPA that consumes the contacts capability — defines client-route layout, server-error mapping to form fields, the place-call outcome UI, and search/list interactions. Kept as a separate capability so the SPA contract can evolve (or be replaced by another client) without rewriting the backend spec, while still being pinnable by acceptance scenarios.

### Modified Capabilities

_None — this is the first capability in the repo._

## Impact

- **New code:** `src/Domain/Contact/**`, `app/Http/Controllers/Api/V1/ContactController.php`, `app/Http/Requests/Contact/*`, `app/Http/Resources/ContactResource.php`, `app/Console/Commands/Contact/*`, `database/migrations/*_create_contacts_*_tables.php`, `database/factories/ContactFactory.php`, `resources/js/pages/contacts/**`, `tests/Feature/Api/V1/ContactsTest.php`, `tests/Unit/Domain/Contact/**`.
- **Config / autoload:** `composer.json` (`autoload.psr-4` adds `Domain\\: src/Domain/`), `phpstan.neon` (scan `src/`), `rector.php` (scan `src/`), `pint.json` (include `src/`).
- **Routes:** new `/api/v1/contacts*` block; SPA route under existing web group.
- **Container bindings:** `TelephonyGateway` → `FakeTelephonyGateway` in `AppServiceProvider`.
- **Dependencies:** none added — sticks to core Laravel + Pest + Tailwind + React already present in the starter (per brief: "limit yourself to packages and dependencies available within the core Laravel framework for core components").
- **Risks:** validating AU/NZ E164 without `giggsey/libphonenumber-for-php` means a hand-rolled regex + length check; documented as a concession in the README with the trade-off (no carrier / number-type detection). Search across phones and emails is a `LIKE` query at this scale — fine for the exercise, flagged in the README as the first thing to swap for FTS / Scout if the table grows.
