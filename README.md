# Rex Contacts Module

A Laravel 13 + PHP 8.5 contacts module: HTTP API, Artisan CLI, queued-job seam, and a React SPA — all routed through a single domain-layer action per operation so the same business rules apply at every entry point.

## Architecture

DDD-lite layout: business logic under `src/Domain/` (PSR-4 mapped in `composer.json`); Eloquent models stay under `app/Models/` for Laravel's discovery and factory conventions.

```
src/Domain/Contact/
├── Actions/                     Single seam — HTTP, CLI, and jobs all dispatch through these
│   ├── UpsertContact.php        write — create or update with transactional child replacement
│   ├── DeleteContact.php        write — idempotent cascade delete
│   ├── GetContact.php           read  — by id, throws ContactNotFoundException
│   ├── ListContacts.php         read  — newest first, with phone/email counts
│   ├── SearchContacts.php       read  — name prefix / E164 phone / exact email (AND)
│   └── PlaceCallToContact.php   side-effect — delegates to TelephonyGateway on primary phone
├── Contracts/
│   └── TelephonyGateway.php     interface — production binding can be swapped without changing callers
├── DataTransferObjects/
│   ├── ContactData.php          mass-assignment allowlist via toModelAttributes()
│   ├── ContactSearchCriteria.php
│   └── CallOutcome.php          status + nullable duration + nullable provider message
├── Enums/
│   └── CallStatus.php           connected | no_answer | busy | failed | invalid_number
├── Exceptions/                  every domain rule surfaces as a domain-typed exception with a stable code
├── Gateways/
│   └── FakeTelephonyGateway.php seeded-random fake — only the seam ships, not real telephony
└── ValueObjects/
    ├── PhoneNumber.php          E164 + AU/NZ region check at construction
    └── EmailAddress.php         RFC syntax + 254-char cap + lowercase normalisation
```

**Per-aggregate rule.** One bounded context per aggregate. `Contact` is the only one here; future aggregates (`Domain\Company\`, `Domain\Deal\`) drop into the same layout.

**Single-action seam.** Chosen so a contact created via HTTP, CLI, or queued job goes through identical validation, transactional boundaries, and error shapes — no entry point can quietly diverge. Controllers (`App\Http\Controllers\Api\V1\*`), Artisan commands (`App\Console\Commands\Contact\*`), and `App\Jobs\UpsertContactJob` all build the same DTO and dispatch the same `Domain\Contact\Actions\*` class; none contain Eloquent or validation rules. Architecture tests in `tests/Unit/Arch/ContactDomainTest.php` pin this, including a check that `App\Jobs\*` can't reach `App\Models\Contact*`.

**Test-first cadence.** Behavioural changes landed test-first — failing test (full body, not a stub), confirm red, implement to green. Cadence recorded in `openspec/changes/contacts-module/tasks.md`. Non-behavioural work (autoload, formatting, migrations, factories) skipped it by design.

**Error envelope.** All 4xx/5xx responses from `/api/v1/contacts*` flow through `App\Http\ApiErrorEnvelope` (wired in `bootstrap/app.php`): `{error: {code, message, details}}` for domain rejections, `{error: {code: "validation_failed", details: [{field, code, message}]}}` for form validation, plus `Retry-After` on `429`. Success responses are not wrapped.

**SPA.** React 19 mounted from the `/contacts*` Blade shell (`resources/views/app.blade.php`). TanStack Query, react-router, react-hook-form + zod (rules mirror the backend). `mapServerErrorsToFields` in `resources/js/api/contacts.ts` routes the server's `details[].field` dot-paths into matching react-hook-form errors, so duplicate-phone/email rejections land inline against the offending input.

## Concessions

Trade-offs sized for the exercise — each has an upgrade path at the seam.

- **Hand-rolled E164 regex (`+(61|64)\d{8,10}`)** instead of `giggsey/libphonenumber-for-php`. Accepts shapes a true libphonenumber check would reject (e.g. AU area codes that don't exist). Swap point: `PhoneNumber::__construct`.
- **`LIKE`-based name search** (exact-match on the normalised email column). Adequate at exercise scale; degrades beyond ~100k rows. `SearchContacts` is the only place to change to swap in Scout/Meilisearch/pg trgm.
- **Deterministic fake telephony.** `FakeTelephonyGateway` is seeded so tests get stable outcomes; production binding is the seam in `AppServiceProvider`.
- **Hard-cap list/search instead of pagination.** Server caps at 50 rows to match the SPA's single-screen affordance. Trivial to swap for `paginate()`.
- **No optimistic locking on upsert.** Two simultaneous PUTs are last-write-wins.

## With more scope

- **Call-log aggregate.** `PlaceCallToContact` returns a `CallOutcome` and discards it. The next aggregate is `Domain\CallLog\` keyed on `(contact_id, called_at)`, repeating the Action/DTO/Exception shape.
- **Soft-delete and audit log.** Cascade delete is hard today. `SoftDeletes` plus an audit channel writing to `contact_events` would slot in behind `DeleteContact` and `UpsertContact` without changing callers.
- **Real telephony adapter.** Bind a Twilio/MessageMedia implementation of `TelephonyGateway` in `AppServiceProvider`; no other file changes.
- **Friendly Artisan errors.** Domain exceptions (e.g. `DuplicateContactPhoneException`) surface in CLI commands as raw stack traces today. Catching them in `App\Console\Commands\Contact\*` and rendering via `$this->error(...)` would mirror the HTTP error envelope's UX.

## AI tooling and oversight

Built with **OpenSpec** (proposal → design → spec → tasks) for thinking and **Claude Code** for the implementation cadence (test-first, per sub-section in `tasks.md`).

- **OpenSpec artifacts** live in `openspec/changes/contacts-module/`: `proposal.md` (why/what), `design.md` (decisions — DDD-lite, VO-at-boundary validation, single-action seam, fake telephony, error envelope), `specs/` (behavioural requirements, one capability per surface), `tasks.md` (12 sections, strict test-first per behavioural group).
- **What AI did.** Drafted the OpenSpec artifacts to my prompts, generated each failing test from the spec scenarios verbatim, then implemented to green.
- **Guardrails that kept the output honest.** Every commit had to pass `composer test` — type coverage 100%, line coverage 100%, phpstan max, pint, rector. Architecture tests in `tests/Unit/Arch/ContactDomainTest.php` pin the single-action seam (no Eloquent in controllers, jobs, or commands; `App\Jobs\*` can't reach `App\Models\Contact*`), so an AI-generated shortcut fails CI rather than landing silently. The SPA section additionally required a real-browser smoke pass via the Pest browser plugin driving Playwright/Chromium.
- **What I checked manually.** Reviewed every diff, verified OpenSpec artifacts matched intent, and used the SPA end-to-end — probing forms with edge-case input and watching empty states, validation feedback, and error surfaces for the rough edges automated tests don't catch.

## Running locally

Requires PHP 8.5, Composer, and [Bun](https://bun.sh).

```bash
composer install
bun install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Start server + queue + Vite dev all at once
composer dev
```

The SPA mounts at `http://127.0.0.1:8000/contacts`. API root: `http://127.0.0.1:8000/api/v1/contacts`.

### Tests

```bash
composer test         # type coverage 100%, line coverage exactly 100%, pint, rector --dry-run, phpstan max
php artisan test      # raw Pest invocation
```

The browser smoke tests under `tests/Browser/` drive real Chromium (Playwright) against the SPA — first run `bunx playwright install chromium`.

### Artisan commands

Each command dispatches the same `Domain\Contact\Actions\*` class as the HTTP layer.

```bash
php artisan contact:upsert --name=Jane --phone=+61412345678 --email=jane@example.com
php artisan contact:show {id}
php artisan contact:search --name=Jan --email=jane@example.com
php artisan contact:call {id}
php artisan contact:delete {id}
```
