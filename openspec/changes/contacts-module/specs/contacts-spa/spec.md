## ADDED Requirements

### Requirement: Contacts SPA scope, mounting, and routing
The system SHALL ship a React Single-Page Application that exercises the majority of the `/api/v1/contacts*` endpoints. The SPA MUST be co-located with the Laravel backend under `resources/js/`, bundled via Vite, and served from a Blade entry view that injects the CSRF token.

#### Scenario: SPA mounts under the existing web group
- **WHEN** a user requests `/contacts` (or any sub-path) in a browser
- **THEN** the system SHALL serve a Blade shell that boots the React SPA, with the CSRF token available to the client

#### Scenario: Top-level client routes are present
- **WHEN** the SPA is loaded
- **THEN** the system SHALL provide client-side routes for `/contacts` (list + search), `/contacts/new` (create), `/contacts/:id` (read + call), and `/contacts/:id/edit` (update)

#### Scenario: SPA exercises the majority of contact API endpoints
- **WHEN** the SPA is in use
- **THEN** it MUST call at minimum: list, search, upsert (both create and update), delete, read-single, and place-call — covering all five operations defined by the contacts capability

### Requirement: SPA validation parity with backend
The system SHALL validate phone numbers and email addresses on the client using rules consistent with the backend (E164 in AU `+61` or NZ `+64` only for phones; RFC-compliant syntax and 254-character cap for emails). Client validation is a UX shortcut, not a security boundary — the backend MUST remain the authoritative validator.

#### Scenario: Client rejects invalid phone before submit
- **WHEN** a user enters a phone in a non-E164 form (e.g. `0412345678`) or a non-AU/NZ region (e.g. `+14155551234`)
- **THEN** the SPA SHALL display an inline error on that field and prevent the submit until the value is valid

#### Scenario: Server validation errors map to form fields
- **WHEN** the API returns `422` with the field-level envelope (`{error: {code: "validation_failed", details: [{field, code, message}, ...]}}`)
- **THEN** the SPA SHALL display each `details[].message` inline against the corresponding field, keyed by the `field` dot-path

#### Scenario: Server domain rejection surfaces against the offending input
- **WHEN** the API returns `422` with a domain code (e.g. `contact.phone.duplicate`) and the offending value in `details`
- **THEN** the SPA SHALL display the message inline next to the input whose value matches `details.phone` (or `details.email`), not as an unscoped page-level toast

#### Scenario: Client rejects within-payload duplicates before submit
- **WHEN** the user submits the contact form with the same phone E164 (or the same email after case normalisation) in two or more rows of the same submission
- **THEN** the SPA SHALL display an inline `duplicate` error on each repeated row and block the submit until the user resolves them
- **AND** if a within-payload duplicate slips past the client and the server returns the field-level validation envelope (`field=phones.N` or `field=emails.N`, `code=duplicate`), the SPA SHALL map those errors inline to the corresponding row inputs via the existing `mapServerErrorsToFields` helper

### Requirement: Place-call UI handles all gateway outcomes
The system SHALL display a distinct UI state for every possible outcome returned by the place-call endpoint, so the operator can tell connected calls from any of the failure modes without inspecting the network response.

#### Scenario: Connected call shows duration
- **WHEN** the API returns `200` with `{status: "connected", duration_seconds: <int>, provider_message: <string>}`
- **THEN** the SPA SHALL display "Connected — Ns" (where N is `duration_seconds`) and keep the call button disabled until the operator dismisses the result

#### Scenario: Non-connected outcomes show status and provider message
- **WHEN** the API returns `200` with `status` in `{no_answer, busy, failed, invalid_number}` and `duration_seconds: null`
- **THEN** the SPA SHALL display a human-readable label for the status alongside the `provider_message`

#### Scenario: No-phone rejection is surfaced before retrying
- **WHEN** the API returns `422` with `code=contact.call.no_phone`
- **THEN** the SPA SHALL show "No phone on file" against the call button and keep it disabled until a phone is added to the contact

#### Scenario: Rate-limited call surfaces retry-after
- **WHEN** the API returns `429` with `code=contact.call.rate_limited` and `details.retry_after_seconds`
- **THEN** the SPA SHALL show "Try again in Ns" and re-enable the call button after that many seconds

### Requirement: SPA list and search interactions
The system SHALL render the list and search endpoints as a single screen with a search affordance, so an operator can find a contact by name, full phone, or email domain without leaving the list view.

#### Scenario: Default list view
- **WHEN** the user opens `/contacts` with no search input
- **THEN** the SPA SHALL render the list endpoint's results (newest first, capped at the server's configured limit) with each row showing name plus phone/email counts

#### Scenario: Search input dispatches to the search endpoint
- **WHEN** the user enters a value in the name, phone, or email-domain search input
- **THEN** the SPA SHALL call `/api/v1/contacts/search` with the corresponding query parameter; multiple inputs MUST be combined as AND on the same request

#### Scenario: Email-domain search accepts a full address and dispatches its domain
- **WHEN** the user enters a value containing `@` in the email search input (e.g. `jane@example.com`)
- **THEN** the SPA SHALL extract the substring after the final `@`, lowercased, and submit that as the `email_domain` query parameter; a bare domain (no `@`) MUST be submitted as-is

#### Scenario: Empty search results render an empty state, not an error
- **WHEN** the search endpoint returns `200` with `{data: []}`
- **THEN** the SPA SHALL display an empty-state message scoped to the active search criteria, not a generic error
