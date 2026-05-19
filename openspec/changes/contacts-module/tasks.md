**Cadence.** For each behavioural group below: write the failing tests (full bodies), run them to confirm they fail, then implement until green. No review pauses between writing tests and implementing — proceed straight through. Non-behavioural tasks (autoload, formatting, file moves, schema migrations, factories) skip the cadence.

## 1. Scaffolding & autoload (non-behavioural)

- [x] 1.1 Add `"Domain\\": "src/Domain/"` to `autoload.psr-4` in `composer.json`; run `composer dump-autoload`
- [x] 1.2 Add `src/` to PHPStan paths in `phpstan.neon`
- [x] 1.3 Add `src/` to Rector paths in `rector.php`
- [x] 1.4 Add `src/` to Pint paths in `pint.json`
- [x] 1.5 Create empty directory skeleton: `src/Domain/Contact/{Actions,DataTransferObjects,ValueObjects,Exceptions,Enums,Contracts,Gateways}` with `.gitkeep`

## 2. Value objects

- [x] 2.1 Write failing `tests/Unit/Domain/Contact/ValueObjects/PhoneNumberTest.php` covering: valid AU mobile, valid AU landline, valid NZ mobile, valid NZ landline, rejects non-E164, rejects US country code, rejects too short, rejects too long, rejects non-digit chars, exposes region. Run, confirm red.
- [x] 2.2 Write failing `tests/Unit/Domain/Contact/ValueObjects/EmailAddressTest.php` covering: lowercases on construction, rejects RFC-invalid, rejects over 254 chars, exposes domain part, equality is case-insensitive. Run, confirm red.
- [x] 2.3 Implement `PhoneNumber` VO + `InvalidPhoneNumberException` (codes: `not_e164`, `unsupported_region`, `malformed`) and `EmailAddress` VO + `InvalidEmailAddressException` (codes: `invalid`, `too_long`). Run until green.

## 3. Migrations & models

- [x] 3.1 `php artisan make:migration create_contacts_table` — `id`, `name` (indexed), timestamps
- [x] 3.2 `php artisan make:migration create_contact_phones_table` — `id`, `contact_id` (constrained, cascade), `e164` (UNIQUE), timestamps
- [x] 3.3 `php artisan make:migration create_contact_emails_table` — `id`, `contact_id` (constrained, cascade), `address` (UNIQUE), `address_domain` (indexed, stored generated column), timestamps
- [x] 3.4 `app/Models/Contact.php` — `$guarded = []`, `hasMany` relationships, `primaryPhone()` via subquery
- [x] 3.5 `app/Models/ContactPhone.php` and `ContactEmail.php` — `$guarded = []`, relationship back to `Contact`
- [x] 3.6 Write failing `tests/Unit/Casts/PhoneNumberCastTest.php` and `EmailAddressCastTest.php` covering: hydrates raw string into VO, serialises VO into raw string, rejects invalid raw string on hydration. Run, confirm red.
- [x] 3.7 Implement `PhoneNumberCast` + `EmailAddressCast`; wire into models. Run until green.
- [x] 3.8 Factories: `ContactFactory` with `withPhone()` / `withEmail()` states; `ContactPhoneFactory`; `ContactEmailFactory` (use `recycle($contact)` for children)
- [x] 3.9 Run migrations against local SQLite; confirm schema with `php artisan db:show`

## 4. DTOs & enums

- [x] 4.1 `Domain\Contact\Enums\CallStatus` enum (`connected`, `no_answer`, `busy`, `failed`, `invalid_number`)
- [x] 4.2 `Domain\Contact\DataTransferObjects\CallOutcome` — `final readonly`, `CallStatus` + nullable duration + nullable provider message
- [x] 4.3 `Domain\Contact\DataTransferObjects\ContactSearchCriteria` — `?string name`, `?PhoneNumber phone`, `?string emailDomain`
- [x] 4.4 Write failing `tests/Unit/Domain/Contact/DataTransferObjects/ContactDataTest.php` covering: `toModelAttributes` returns only contact-table columns (not phones/emails), constructor accepts typed VO arrays, rejects raw strings for phones / emails. Run, confirm red.
- [x] 4.5 Implement `ContactData` with `toModelAttributes()`. Run until green.

## 5. Actions — writes

- [x] 5.1 Write failing `tests/Unit/Domain/Contact/Actions/UpsertContactTest.php` covering: creates contact + children, updates existing contact (replaces child sets), rejects when no identifiers supplied, rejects duplicate phone across contacts, rejects duplicate email across contacts, rolls back entire transaction on mid-write failure. Run, confirm red.
- [x] 5.2 Write failing `tests/Unit/Domain/Contact/Actions/DeleteContactTest.php` covering: deletes contact, cascades phones, cascades emails, is idempotent. Run, confirm red.
- [x] 5.3 Implement `UpsertContact::execute` (transactional, child-set replacement, duplicate checks) and `DeleteContact::execute`. Run until green.

## 6. Actions — reads

- [x] 6.1 Write failing `tests/Unit/Domain/Contact/Actions/GetContactTest.php`, `ListContactsTest.php`, `SearchContactsTest.php` covering: `GetContact` returns contact with eager-loaded children and throws `ContactNotFoundException` on missing id; `ListContacts` returns newest first, respects limit, returns empty collection when none exist, includes phone + email counts without loading rows; `SearchContacts` matches name prefix (case-insensitive), exact E164 phone, email domain (case-insensitive), AND-combines multiple criteria, rejects empty criteria. Run, confirm red.
- [x] 6.2 Implement `GetContact`, `ListContacts`, `SearchContacts` + `ContactNotFoundException`. Run until green.

## 7. Telephony gateway

- [x] 7.1 Write failing `tests/Unit/Domain/Contact/Gateways/FakeTelephonyGatewayTest.php` covering: returns one of the five `CallStatus` values, deterministic given the seed, connected outcome has duration, non-connected has null duration. Run, confirm red.
- [x] 7.2 Write failing `tests/Unit/Domain/Contact/Actions/PlaceCallToContactTest.php` covering: calls gateway with primary phone (oldest by created_at), returns gateway outcome verbatim, rejects contact with zero phones, does not call gateway when no phones. Run, confirm red.
- [x] 7.3 Implement `TelephonyGateway` interface, `FakeTelephonyGateway` (seeded), `PlaceCallToContact`, `ContactHasNoPhoneException`; bind in `AppServiceProvider`. Run until green.

## 8. HTTP layer

- [x] 8.1 Write failing feature tests covering every spec scenario for each endpoint: `tests/Feature/Api/V1/Contacts/UpsertContactTest.php` (POST creates 201 with Location, PUT updates 200, PUT missing returns 404, invalid phone returns 422 with `code=contact.phone.*`, invalid email returns 422, duplicate phone/email return 422, no-identifier returns 422), plus `ListContactsTest.php`, `ShowContactTest.php`, `SearchContactsTest.php`, `DeleteContactTest.php`, `PlaceCallTest.php`. Run, confirm red.
- [x] 8.2 Implement `ContactResource`, `StoreContactRequest`, `UpdateContactRequest` (with `toContactData()`), `SearchContactsRequest`, `ContactController` (`store`, `update`, `index`, `show`, `search`, `destroy`, `call`), route bindings + `POST /{contact}/call` with `throttle:10,1`, and the exception renderer in `bootstrap/app.php` that maps VO + duplicate exceptions through the `ApiErrorEnvelope` formatter (see 8.4). Run until green.
- [x] 8.3 Write failing `tests/Feature/Api/V1/ErrorEnvelopeTest.php` covering: field-level validation envelope (`{error: {code: "validation_failed", details: [{field, code, message}, ...]}}` at `422`), domain rejection envelope (`{error: {code, message, details: {...}}}` with offending value e.g. `details.phone` / `details.email` / `details.supported_regions`), `404` `contact.not_found` envelope with `details.id`, `429` `contact.call.rate_limited` envelope with `details.retry_after_seconds` AND `Retry-After` header, 2xx responses are NOT wrapped in `error`. Run, confirm red.
- [x] 8.4 Implement `App\Http\ApiErrorEnvelope` formatter wired into `bootstrap/app.php`'s exception renderer so every 4xx/5xx response from `/api/v1/contacts*` emits the spec'd shape (including `Retry-After` for 429). Run until green.

## 9. Additional action entry points (CLI + queued jobs)

- [x] 9.1 Write failing `tests/Feature/Console/Contact/UpsertContactCommandTest.php`, `DeleteContactCommandTest.php`, `SearchContactsCommandTest.php`, `ShowContactCommandTest.php`, `CallContactCommandTest.php` covering: each command dispatches the same action as the HTTP layer, prints expected output, rejects the same validation errors as HTTP. Run, confirm red.
- [x] 9.2 Implement `contact:upsert`, `contact:delete`, `contact:search`, `contact:show`, `contact:call` (each dispatches the same action used by the HTTP layer). Run until green.
- [x] 9.3 Write failing `tests/Unit/Domain/Contact/ValueObjects/QueueSerialisationTest.php` (`PhoneNumber` survives `serialize`/`unserialize` round-trip preserving value + region, `EmailAddress` survives round-trip preserving normalised form, tampered serialised payloads raise the same `InvalidPhoneNumberException` / `InvalidEmailAddressException` with the same `code` as fresh construction) and `tests/Unit/Jobs/UpsertContactJobTest.php` (job's `handle()` dispatches `UpsertContact` with a `ContactData` DTO, constructor-injected `ContactData` survives Laravel's queue serialisation, validation failures surface the same domain exception as HTTP/CLI, job class contains zero Eloquent calls). Run, confirm red.
- [x] 9.4 Implement a thin `App\Jobs\UpsertContactJob` (constructor takes `ContactData`; `handle()` dispatches `UpsertContact::execute`); no production caller yet — the class exists to prove the seam and to be picked up by the ArchTest in 11.3. Run until green.

## 10. React SPA frontend (UI verified via real-browser smoke test per starter convention)

- [x] 10.1 Add React + react-router + TanStack Query + react-hook-form + zod; `bun install`
- [x] 10.2 `resources/views/app.blade.php` SPA shell
- [x] 10.3 `resources/js/app.tsx` — router + query client + layout
- [x] 10.4 `resources/js/api/contacts.ts` — typed fetchers parsing the `{error: {code, message, details}}` envelope; `mapServerErrorsToFields(details)` helper that turns `details[].field` dot-paths into form-field errors for react-hook-form
- [x] 10.5 `resources/js/pages/contacts/Index.tsx` — single-screen list + search affordances (name / phone / email-domain inputs combined as AND); empty results render a scoped empty state, not an error
- [x] 10.6 `resources/js/pages/contacts/Show.tsx` — detail + "Place Call" button + outcome panel that distinctly renders `connected` (with duration), `no_answer` / `busy` / `failed` / `invalid_number` (with provider message), `contact.call.no_phone` (disabled-state explanation), and `contact.call.rate_limited` (countdown using `details.retry_after_seconds`)
- [x] 10.7 `resources/js/pages/contacts/Form.tsx` — shared create/edit with zod schema mirroring backend rules (E164 AU/NZ, RFC email, 254-char cap); on submit failure, route validation errors through `mapServerErrorsToFields` so each `details[].field` lands against the right input
- [x] 10.8 `composer dev` smoke-test in a real browser per [[feedback_smoke_test_ui_in_browser]]: list → search (verify empty state) → create (verify zod inline error on `0412345678`) → trigger duplicate-phone server error (verify inline placement) → call (verify each outcome variant including rate-limited countdown if reachable) → check DevTools console clean

## 11. Architecture & quality gates

- [x] 11.1 `tests/Unit/Arch/ContactDomainTest.php` — pins `Domain\Contact\` to `Actions|DataTransferObjects|ValueObjects|Exceptions|Enums|Contracts|Gateways` sub-namespaces only
- [x] 11.2 ArchTest pinning `App\Http\Controllers\Api\V1\*` away from direct `App\Models\Contact*` query references
- [x] 11.3 ArchTest pinning `App\Jobs\*` (and any other async/scheduled entry point) away from direct `App\Models\Contact*` query references — must dispatch through `Domain\Contact\Actions\*`
- [x] 11.4 `vendor/bin/pint --format agent` — clean
- [x] 11.5 `vendor/bin/phpstan analyse` — clean at level max
- [x] 11.6 `vendor/bin/rector process --dry-run` — clean
- [x] 11.7 `php artisan test --compact` — full suite green

## 12. Documentation & submission

- [x] 12.1 README — "Architecture" section: `src/Domain/`, per-aggregate rule, test-first cadence used to build this module
- [x] 12.2 README — "Concessions" section: hand-rolled E164, `LIKE`-based search, no soft-delete / audit, no call log persisted, deterministic fake telephony
- [x] 12.3 README — "AI tooling" section: OpenSpec (proposal → design → spec → tasks) + Claude Code test-first cadence; what AI did vs what I checked
- [x] 12.4 README — "Running locally": `composer install`, `bun install`, `.env`, migrate, `composer dev`, test commands
- [x] 12.5 Open the PR from `feat/contacts-module` against `main` on `i-bajrai/rex-app`; PR body links the OpenSpec change directory
- [ ] 12.6 `openspec archive contacts-module` after the PR is merged

## 13. Within-payload duplicate validation

- [x] 13.1 Add Laravel `distinct` rule on `phones.*` and `distinct:ignore_case` on `emails.*` in `UpsertContactRequest`, with custom human-readable messages
- [x] 13.2 Extend `ApiErrorEnvelope::buildValidationDetails` to derive each detail's `code` from the failed Laravel rule short-name (`Distinct` -> `duplicate`) using `$exception->validator->failed()` instead of hardcoding `invalid`
- [x] 13.3 Backend feature tests: POST + PUT with duplicate phones AND duplicate emails (case-insensitive) assert 422 `validation_failed` with `details[]` containing `field=phones.N` / `emails.N` and `code=duplicate`; PUT tests also assert the DB row is unchanged (request never reaches `UpsertContact::execute`)
- [x] 13.4 Frontend zod schema: `superRefine` flags duplicate phones (exact) and duplicate emails (case-insensitive after lowercasing) within the form arrays; each duplicate row gets an inline error before submit
- [x] 13.5 Browser test: typing the same email twice surfaces the inline duplicate error and blocks any network call (`Contact::count()` stays at 0)
- [x] 13.6 `composer test` green (143 tests, 469 assertions, type coverage 100%, code coverage 100%, pint + rector + phpstan + vp fmt clean)
- [x] 13.7 `bun run build` green

## 14. Call outcome envelope fix

- [x] 14.1 `resources/js/api/contacts.ts` — change `placeCall` to return `Promise<{data: CallOutcome}>` so it matches the `{data: T}` envelope convention used by sibling fetchers (`getContact`, `createContact`, `updateContact`)
- [x] 14.2 `resources/js/pages/contacts/Show.tsx` — unwrap `response.data` in the `callMutation.onSuccess` callback so the outcome panel renders the human-readable status label instead of blank
- [x] 14.3 Browser test extension in `tests/Browser/ContactsSpaTest.php`: bind a stub `TelephonyGateway` returning `CallStatus::NoAnswer`, click the `@place-call` testid, and assert the rendered "No answer" label and provider message are visible
- [x] 14.4 `composer test` green
- [x] 14.5 `bun run build` green

## 15. Root redirect to /contacts

- [x] 15.1 `routes/web.php` — replace welcome-view at `/` with `Route::redirect('/', '/contacts')` (302); drop the now-unused `View` import
- [x] 15.2 Delete `resources/views/welcome.blade.php` and `tests/Browser/WelcomeTest.php` — no longer part of the contacts SPA flow
- [x] 15.3 `tests/Feature/RootRedirectTest.php` — assert `GET /` returns `302` with `Location: /contacts`
- [x] 15.4 Browser test: `visit('/')` lands at `/contacts` with the contacts page rendered
- [x] 15.5 `composer test` green
