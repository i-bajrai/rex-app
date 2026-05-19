# Rex Contacts Module

A Laravel 13 + PHP 8.5 contacts module: HTTP API, Artisan CLI, queued-job seam, and a React SPA — all routed through a single domain-layer action per operation so the same business rules apply at every entry point.

## Architecture

This module is the first vertical slice of a DDD-lite layout. All custom business logic lives under `src/Domain/` (PSR-4 mapped via `composer.json`); Eloquent models stay under `app/Models/` for Laravel's discovery and factory conventions.

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

**Per-aggregate rule.** One bounded context per aggregate. `Contact` is the only aggregate in this exercise; the directory layout is intentionally repeatable for future aggregates (`Domain\Company\`, `Domain\Deal\`, etc.) without reshaping the rest of the codebase.

**Single-action seam.** HTTP controllers (`App\Http\Controllers\Api\V1\*`), Artisan commands (`App\Console\Commands\Contact\*`), and the `App\Jobs\UpsertContactJob` queued-job seam all build the same DTO and dispatch the same `Domain\Contact\Actions\*` class. None of them contain Eloquent query chains or validation rules — architecture tests in `tests/Unit/Arch/ContactDomainTest.php` pin this, including a check that `App\Jobs\*` doesn't reach for `App\Models\Contact*`.

**Test-first cadence.** Every behavioural change in this module landed test-first: write the failing test (full bodies, not stubs), run to confirm red, then implement until green. The cadence is recorded in `openspec/changes/contacts-module/tasks.md`. Non-behavioural work (autoload, formatting, file moves, schema migrations, factories) skipped the cadence by design.

**Error envelope.** Every 4xx/5xx response from `/api/v1/contacts*` flows through `App\Http\ApiErrorEnvelope` (wired in `bootstrap/app.php`), so consumers get a stable shape: `{error: {code, message, details}}` for domain rejections, `{error: {code: "validation_failed", details: [{field, code, message}]}}` for form-request validation, plus `Retry-After` on `429`. Success responses are never wrapped.

**SPA.** `resources/js/app.tsx` mounts a React 19 SPA from the `/contacts*` Blade shell (`resources/views/app.blade.php`). State via TanStack Query; routing via react-router; forms via react-hook-form + zod (E164 AU/NZ, RFC email, 254-char cap mirroring the backend). The `mapServerErrorsToFields` helper in `resources/js/api/contacts.ts` routes the server's `details[].field` dot-paths into the matching react-hook-form errors so duplicate-phone / duplicate-email server rejections land inline against the offending input.

## Concessions

These are deliberate trade-offs sized for the exercise — each has a clear upgrade path documented at the point of use.

- **Hand-rolled E164 regex (`+(61|64)\d{8,10}`)** instead of `giggsey/libphonenumber-for-php`. Accepts shapes a true libphonenumber check would reject (e.g. AU area codes that don't exist). The seam is `PhoneNumber::__construct` — single-file swap when needed.
- **`LIKE`-based name search** (and exact-match on the normalised email column). Adequate at exercise scale; degrades beyond ~100k rows. `SearchContacts` action is the only place that needs to change to swap in Scout/Meilisearch/pg trgm.
- **No soft-delete, audit log, or call-log persistence.** `PlaceCallToContact` returns a `CallOutcome` DTO and discards it. The obvious next aggregate is `Domain\CallLog\` keyed on `(contact_id, called_at)`.
- **Deterministic fake telephony.** `FakeTelephonyGateway` is seeded so tests get stable outcomes; the production binding is the seam point in `AppServiceProvider`. Real provider not in scope.
- **Hard-cap list/search instead of pagination.** Server caps at 50 rows; matches the SPA's single-screen affordance. Trivial to swap for `paginate()` if needed.
- **No optimistic locking on upsert.** Two simultaneous PUTs are last-write-wins. Out of scope for the exercise.

## AI tooling

This module was built using **OpenSpec** (proposal → design → spec → tasks) for upfront thinking, and **Claude Code** for the implementation cadence (test-first, run-to-red, implement-until-green, per sub-section in `tasks.md`).

- **OpenSpec artifacts** live in `openspec/changes/contacts-module/`: `proposal.md` framed the why and what, `design.md` recorded the decisions (DDD-lite layout, VO-at-the-boundary validation, single-action seam, fake telephony gateway, error envelope shape), `specs/` captured the behavioural requirements (one capability for the backend, one for the SPA), and `tasks.md` broke implementation into 12 sequential sections with a strict test-first cadence per behavioural group.
- **What AI did.** Drafted the OpenSpec artifacts to my prompts, generated each failing test from the spec scenarios verbatim, then implemented until green. Each section's commit landed only after `composer test` (type coverage, 100% line coverage, pint, rector, phpstan) was green; the SPA section additionally landed only after a real-browser smoke pass via the Pest browser plugin driving Playwright/Chromium.
- **What I checked.** Reviewed diffs, verified the OpenSpec artifacts, and used the application as a tester — walking the SPA end-to-end, probing forms with edge-case input, watching how empty states, validation feedback, and error surfaces behaved, and looking for the kind of small UX rough edges automated tests don't catch.

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
