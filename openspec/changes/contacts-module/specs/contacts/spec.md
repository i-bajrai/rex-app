## ADDED Requirements

### Requirement: Contact aggregate shape
The system SHALL represent a contact as a record with a name, zero or more phone numbers, and zero or more email addresses. Phone numbers and email addresses MUST each be unique across the entire dataset.

#### Scenario: Contact has at least one identifier
- **WHEN** a contact is created or updated
- **THEN** the system SHALL require either at least one phone number OR at least one email address; a name-only contact is rejected with a `422` `code=contact.no_identifiers`

#### Scenario: Duplicate phone number across contacts
- **WHEN** a phone number already attached to contact A is included in an upsert for contact B
- **THEN** the system SHALL reject the upsert with `422` `code=contact.phone.duplicate` and the offending E164 value in the error payload

#### Scenario: Duplicate email address across contacts
- **WHEN** an email address (case-insensitive) already attached to contact A is included in an upsert for contact B
- **THEN** the system SHALL reject the upsert with `422` `code=contact.email.duplicate` and the offending normalised address in the error payload

### Requirement: Phone-number validation (E164, AU/NZ only)
The system SHALL accept phone numbers only in E164 format and only when the country code is Australia (`+61`) or New Zealand (`+64`). Validation MUST occur at the value-object boundary, so the rule holds identically for HTTP, CLI, and any future entry point.

#### Scenario: Valid Australian mobile
- **WHEN** `+61412345678` is submitted
- **THEN** the system SHALL accept it and persist it verbatim

#### Scenario: Valid New Zealand mobile
- **WHEN** `+64211234567` is submitted
- **THEN** the system SHALL accept it and persist it verbatim

#### Scenario: Non-E164 input is rejected
- **WHEN** `0412345678` (local format, no `+`) is submitted
- **THEN** the system SHALL reject with `422` `code=contact.phone.not_e164`

#### Scenario: Out-of-region country code is rejected
- **WHEN** `+14155551234` (US) is submitted
- **THEN** the system SHALL reject with `422` `code=contact.phone.unsupported_region` and the supported regions (`AU`, `NZ`) in the error payload

#### Scenario: Malformed input is rejected
- **WHEN** the value contains characters other than `+` and digits (e.g. `+61 412-345-678`)
- **THEN** the system SHALL reject with `422` `code=contact.phone.malformed`

### Requirement: Email-address validation
The system SHALL accept email addresses that pass RFC-compliant syntax validation and are at most 254 characters. Addresses MUST be normalised to lowercase before storage and comparison.

#### Scenario: Mixed-case email is normalised
- **WHEN** `Jane.Doe@Example.COM` is submitted
- **THEN** the system SHALL persist it as `jane.doe@example.com` and treat it as equal to any earlier lowercase variant

#### Scenario: Syntactically invalid email is rejected
- **WHEN** `not-an-email` is submitted
- **THEN** the system SHALL reject with `422` `code=contact.email.invalid`

#### Scenario: Over-length email is rejected
- **WHEN** an address longer than 254 characters is submitted
- **THEN** the system SHALL reject with `422` `code=contact.email.too_long`

### Requirement: Upsert contact (create or update)
The system SHALL expose a single upsert operation that creates a new contact when no identifier is supplied and updates the matching contact when one is. On update, the supplied phones and emails MUST replace the existing child sets atomically (within a single DB transaction).

#### Scenario: Create new contact via HTTP
- **WHEN** the client `POST`s `/api/v1/contacts` with a name, one phone, one email, and no id
- **THEN** the system SHALL create the contact, return `201` with the full resource, and include a `Location` header pointing to `/api/v1/contacts/{id}`

#### Scenario: Update existing contact via HTTP
- **WHEN** the client `PUT`s `/api/v1/contacts/{id}` with a new name and a different set of phones / emails
- **THEN** the system SHALL replace the contact's name and child sets in a single transaction and return `200` with the updated resource

#### Scenario: Update missing contact returns 404
- **WHEN** the client `PUT`s `/api/v1/contacts/{id}` for an id that does not exist
- **THEN** the system SHALL return `404` `code=contact.not_found`

#### Scenario: Upsert via CLI dispatches the same action
- **WHEN** an operator runs `php artisan contact:upsert --name=Jane --phone=+61412345678 --email=jane@example.com`
- **THEN** the system SHALL invoke the same `UpsertContact` action as the HTTP path and print the resulting contact id

#### Scenario: Partial child replacement is atomic
- **WHEN** an update transaction fails halfway through writing child rows
- **THEN** the system SHALL roll back the entire upsert so the contact is left in its pre-call state

### Requirement: Read single contact
The system SHALL allow retrieval of a single contact by id, including all phones and emails in a single response.

#### Scenario: Existing contact is returned with children
- **WHEN** the client `GET`s `/api/v1/contacts/{id}` for an existing id
- **THEN** the system SHALL return `200` with the contact, all phones (as E164 strings), and all emails (lowercase)

#### Scenario: Missing contact returns 404
- **WHEN** the client `GET`s `/api/v1/contacts/{id}` for an id that does not exist
- **THEN** the system SHALL return `404` `code=contact.not_found`

#### Scenario: Read via CLI dispatches the same action
- **WHEN** an operator runs `php artisan contact:show {id}`
- **THEN** the system SHALL invoke the same `GetContact` action and print the contact as JSON

### Requirement: List contacts
The system SHALL return contacts in reverse-chronological creation order (newest first), capped at a configurable limit. Each row in the list MUST include the contact's id, name, and counts of phones and emails (not the full child rows).

#### Scenario: Default list is newest first
- **WHEN** the client `GET`s `/api/v1/contacts`
- **THEN** the system SHALL return up to 50 contacts ordered by `created_at DESC`

#### Scenario: Empty list returns 200 with empty data
- **WHEN** there are no contacts and the client `GET`s `/api/v1/contacts`
- **THEN** the system SHALL return `200` with `data: []`, not `404`

### Requirement: Search contacts (name, phone, email-domain)
The system SHALL allow searching contacts on three orthogonal axes: name (case-insensitive prefix match), full phone number (exact match in E164), and email domain (case-insensitive exact match on the part after `@`). At least one criterion MUST be supplied; multiple criteria are combined with AND.

#### Scenario: Search by name prefix
- **WHEN** the client `GET`s `/api/v1/contacts/search?name=jan`
- **THEN** the system SHALL return every contact whose name starts with "jan" (case-insensitive), ordered by `created_at DESC`

#### Scenario: Search by phone (exact E164)
- **WHEN** the client `GET`s `/api/v1/contacts/search?phone=%2B61412345678`
- **THEN** the system SHALL return the contact that owns that phone, or an empty list if none does

#### Scenario: Search by email domain
- **WHEN** the client `GET`s `/api/v1/contacts/search?email_domain=Example.com`
- **THEN** the system SHALL return every contact with at least one email whose domain (post-`@` part) equals `example.com` (case-insensitive)

#### Scenario: Combined criteria are AND-ed
- **WHEN** the client `GET`s `/api/v1/contacts/search?name=jan&email_domain=example.com`
- **THEN** the system SHALL return only contacts that match BOTH criteria

#### Scenario: Empty search is rejected
- **WHEN** the client `GET`s `/api/v1/contacts/search` with no criteria
- **THEN** the system SHALL reject with `422` `code=contact.search.no_criteria`

#### Scenario: Phone search input is validated
- **WHEN** the client supplies a `phone` query param that is not valid E164 AU/NZ
- **THEN** the system SHALL reject with `422` `code=contact.phone.not_e164` or `code=contact.phone.unsupported_region` as appropriate

#### Scenario: Search via CLI dispatches the same action
- **WHEN** an operator runs `php artisan contact:search --email-domain=example.com`
- **THEN** the system SHALL invoke the same `SearchContacts` action as the HTTP path and print the matching contacts as JSON

### Requirement: Delete contact
The system SHALL delete a contact and all its phones and emails in a single transaction. Deletion MUST be idempotent — deleting an already-deleted contact returns the same response as the first deletion.

#### Scenario: Delete existing contact via HTTP
- **WHEN** the client `DELETE`s `/api/v1/contacts/{id}` for an existing id
- **THEN** the system SHALL delete the contact and its children and return `204`

#### Scenario: Delete missing contact returns 404
- **WHEN** the client `DELETE`s `/api/v1/contacts/{id}` for an id that has never existed
- **THEN** the system SHALL return `404` `code=contact.not_found`

#### Scenario: Delete via CLI dispatches the same action
- **WHEN** an operator runs `php artisan contact:delete {id}`
- **THEN** the system SHALL invoke the same `DeleteContact` action and print a confirmation line

### Requirement: Place a call to a contact (mocked)
The system SHALL expose a "place call" operation that targets the contact's primary phone (oldest `contact_phones` row by `created_at`) and returns a structured outcome. A real telephony provider is NOT in scope; the operation MUST be implemented against a `TelephonyGateway` interface so the production binding can be swapped without changing call sites.

#### Scenario: Call a contact with a primary phone
- **WHEN** the client `POST`s `/api/v1/contacts/{id}/call`
- **THEN** the system SHALL invoke `TelephonyGateway::call` with the contact's primary phone and return `200` with `{status, duration_seconds, provider_message}` where `status` is one of `connected | no_answer | busy | failed | invalid_number`

#### Scenario: Connected call returns a duration
- **WHEN** the gateway returns `status=connected`
- **THEN** the response payload MUST include a non-null `duration_seconds` integer

#### Scenario: Non-connected call has null duration
- **WHEN** the gateway returns any status other than `connected`
- **THEN** `duration_seconds` MUST be `null`

#### Scenario: Call a contact with no phones
- **WHEN** the client `POST`s `/api/v1/contacts/{id}/call` for a contact that has zero phones
- **THEN** the system SHALL reject with `422` `code=contact.call.no_phone` without invoking the gateway

#### Scenario: Call rate is limited
- **WHEN** the same client exceeds the configured call rate (10 calls per minute)
- **THEN** the system SHALL respond with `429` `code=contact.call.rate_limited`

#### Scenario: Call via CLI dispatches the same action
- **WHEN** an operator runs `php artisan contact:call {id}`
- **THEN** the system SHALL invoke the same `PlaceCallToContact` action and print the `CallOutcome` as JSON

### Requirement: Domain layer placement and discipline
The system SHALL implement all custom business logic for contacts under the `Domain\Contact\` namespace (PSR-4 mapped to `src/Domain/Contact/`). Controllers, console commands, and other entry points MUST dispatch to actions in this namespace and MUST NOT contain Eloquent query chains or business rules.

#### Scenario: ArchTest pins Domain layer composition
- **WHEN** the architecture test suite runs
- **THEN** classes under `Domain\Contact\` MUST live only inside `Actions`, `DataTransferObjects`, `ValueObjects`, or `Exceptions` sub-namespaces

#### Scenario: ArchTest pins controllers thin
- **WHEN** the architecture test suite runs
- **THEN** classes under `App\Http\Controllers\Api\V1\` MUST NOT directly reference `App\Models\Contact` or any of its child models in query chains; they MUST go through `Domain\Contact\Actions\*`

### Requirement: Single action seam for HTTP, CLI, and asynchronous entry points
The system SHALL route every contact operation through a single `Domain\Contact\Actions\*` class. HTTP controllers, Artisan commands, and queued or scheduled jobs MUST each construct the operation's DTO and dispatch that action — none MAY contain Eloquent query chains, validation, or other business rules. This guarantees identical behaviour whether the operation runs synchronously, asynchronously, or from a CLI entry point, as required by the brief.

#### Scenario: Queued job upsert dispatches the same action as HTTP and CLI
- **WHEN** a queued job (e.g. a bulk-import worker) re-hydrates a `ContactData` DTO from its serialised payload and dispatches `UpsertContact`
- **THEN** the system SHALL apply identical validation, transactional behaviour, and error semantics as the HTTP and CLI paths; no Eloquent calls or validation rules MAY exist inside the job class

#### Scenario: Value objects revalidate after queue serialisation
- **WHEN** a job's payload is dequeued and a `PhoneNumber` or `EmailAddress` value object is reconstructed from its serialised form
- **THEN** the value-object constructor MUST re-run the E164-AU/NZ and email rules on the dequeued data; an invalid value MUST raise the same domain exception HTTP and CLI would surface, with the same `code` value

#### Scenario: ArchTest forbids duplicate logic in async entry points
- **WHEN** the architecture test suite runs
- **THEN** classes under `App\Jobs\` (and any other queued/scheduled entry point) MUST NOT reference Eloquent query builders for contacts, child phones, or child emails; they MUST dispatch through `Domain\Contact\Actions\*`

### Requirement: API error response envelope
The system SHALL return errors from `/api/v1/contacts*` in a consistent JSON envelope so third-party API consumers can branch on a stable `code` and surface informative messages to end users. Every error referenced in the Requirements above MUST be expressed through this envelope.

#### Scenario: Field-level validation error envelope
- **WHEN** a request fails Form Request validation
- **THEN** the response body MUST match `{"error": {"code": "validation_failed", "message": <string>, "details": [{"field": <dot.path>, "code": <string>, "message": <string>}, ...]}}` with HTTP `422`

#### Scenario: Domain rejection envelope
- **WHEN** a request fails a domain rule (e.g. `contact.phone.duplicate`, `contact.phone.unsupported_region`, `contact.no_identifiers`, `contact.call.no_phone`)
- **THEN** the response body MUST match `{"error": {"code": <domain code>, "message": <string>, "details": {<context fields>}}}` and the `code` value MUST exactly match the value referenced in the Requirement that rejected the request

#### Scenario: Offending value is included in details
- **WHEN** a rejection concerns a specific value (a duplicate phone, an unsupported region, a malformed email)
- **THEN** the `details` object MUST include that value (e.g. `{"phone": "+14155551234", "supported_regions": ["AU", "NZ"]}`) so consumers can surface it without re-parsing the message string

#### Scenario: Not-found envelope
- **WHEN** any endpoint returns `404` for a missing contact
- **THEN** the response body MUST be `{"error": {"code": "contact.not_found", "message": <string>, "details": {"id": <requested id>}}}`

#### Scenario: Rate-limit envelope
- **WHEN** the call endpoint returns `429`
- **THEN** the response body MUST be `{"error": {"code": "contact.call.rate_limited", "message": <string>, "details": {"retry_after_seconds": <int>}}}` AND the response MUST include a `Retry-After` header with the same value in seconds

#### Scenario: Successful responses are not wrapped in the error envelope
- **WHEN** any 2xx response is returned
- **THEN** the body MUST be the resource shape directly (or `{"data": [...]}` for collections), never wrapped in `{"error": ...}`
