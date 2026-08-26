## Purpose

Owns the candidate-facing duplicate report and cleanup endpoints for the trusted profile: detection listing, profile-item merge, candidate-skill merge, and language deduplication — with structural server-side validation, ownership always derived from the authenticated user, and consistent RFC 9457 problem-details errors.

## ADDED Requirements

### Requirement: PDC-001 - Duplicate report listing
The system SHALL expose `GET /api/v1/profile/duplicates` returning duplicate groups detected on the authenticated user's profile (exact and possible), computed by the existing deterministic detector. A user without a profile SHALL receive an empty report rather than an error.

#### Scenario: Report for own profile
- **WHEN** an authenticated user requests their duplicates report
- **THEN** the response contains groups and a summary scoped exclusively to that user's profile

#### Scenario: No profile yields empty report
- **WHEN** an authenticated user without a candidate profile requests the report
- **THEN** the response is HTTP 200 with empty groups

### Requirement: PDC-002 - Profile item merge validation
`POST /api/v1/profile/cleanup/items` SHALL validate structurally before any mutation: `keep_id` required integer referencing an owned profile item; `duplicate_ids` required non-empty array of integers, all distinct; `keep_id` SHALL NOT appear inside `duplicate_ids`; optional `allow_possible` boolean. Violations SHALL return HTTP 422 problem-details validation errors naming the offending field.

#### Scenario: Valid merge accepted
- **WHEN** a valid keep/duplicate pair of same-type items owned by the caller is submitted
- **THEN** the merge executes transactionally and returns the merged result

#### Scenario: Malformed payload rejected
- **WHEN** `duplicate_ids` is missing, empty, contains non-distinct values, or includes `keep_id`
- **THEN** the system returns HTTP 422 with a field-level validation error and performs no mutation

### Requirement: PDC-003 - Skill merge validation
`POST /api/v1/profile/cleanup/skills` SHALL apply equivalent structural validation: required integer `keep_id`, required non-empty distinct `duplicate_ids` array, `keep_id` not included in `duplicate_ids`. Ownership SHALL resolve through the authenticated user's profile only.

#### Scenario: Invalid skill merge rejected
- **WHEN** the skill merge payload is malformed per the structural rules
- **THEN** the system returns HTTP 422 with field-level errors and no rows change

### Requirement: PDC-004 - Ownership scoping on merge
Every cleanup/merge operation SHALL derive ownership from the authenticated user's candidate profile and SHALL never accept a client-supplied profile identifier. Any referenced item or skill belonging to another user SHALL be treated as not part of the caller's profile, producing a validation error or 404 consistent with existing conventions — never a cross-user mutation.

#### Scenario: Cross-user identifiers rejected
- **WHEN** a merge request references ids owned by another user
- **THEN** the system rejects the request with a safe error and mutates nothing

#### Scenario: Client-supplied profile ignored
- **WHEN** a request body attempts to supply a different profile id
- **THEN** the system ignores it and scopes all work to the authenticated user's profile

### Requirement: PDC-005 - Consistent error contract
All cleanup endpoints SHALL return RFC 9457-style problem details with stable codes (`validation_error`, plus existing action-level conflict codes) and the propagated `request_id`.

#### Scenario: Problem details shape
- **WHEN** any cleanup endpoint fails validation or conflicts
- **THEN** the response body includes `type`, `title`, `status`, `detail`, `code`, `errors`, and `request_id`
