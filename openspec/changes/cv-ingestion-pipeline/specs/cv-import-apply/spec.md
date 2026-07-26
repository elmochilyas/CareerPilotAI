## ADDED Requirements

### Requirement: Candidate previews import before applying
The system SHALL generate an import preview showing what will change.

#### Scenario: Preview complete review
- **GIVEN** the candidate has reviewed all suggestions for a CV (none pending)
- **WHEN** they call GET `/api/v1/cv/{cvDocument}/import-preview`
- **THEN** the system SHALL return HTTP 200 with a summary of accepted, edited, rejected, keep_existing, create_new, and update_existing items
- **AND** include conflict detection for duplicate items
- **AND** set `"ready": true`

#### Scenario: Preview with pending reviews
- **GIVEN** the candidate has not reviewed all suggestions
- **WHEN** they call GET `/api/v1/cv/{cvDocument}/import-preview`
- **THEN** the system SHALL return HTTP 409 with `not_all_reviewed` code

### Requirement: Candidate applies import
The system SHALL apply accepted suggestions to the candidate profile in a single transaction.

#### Scenario: Successful import with all accepted
- **GIVEN** the candidate has accepted 2 field updates, 3 new profile items, and 2 skills
- **WHEN** they call POST `/api/v1/cv/{cvDocument}/apply` with an `Idempotency-Key` header
- **THEN** the system SHALL:
  - Update headline and summary on `candidate_profiles`
  - Create 3 new `profile_items` rows
  - Create 2 `candidate_skills` rows with `state: claimed`
  - Create a `cv_import_batch` with `status: applied`
  - Update document status to `imported`
  - Update suggestion `review_status` to `imported`
  - Return HTTP 200 with `CvImportBatchResource`

#### Scenario: Import creates skills as claimed
- **GIVEN** the candidate accepted 2 skill suggestions
- **WHEN** the import is applied
- **THEN** both skills SHALL be created with `state: 'claimed'`
- **AND** the skill evidence SHALL reference the CV document ID and source text

#### Scenario: Import with cross-user ownership returns 404
- **GIVEN** candidate A has a CV with reviewed suggestions
- **WHEN** candidate B calls POST `/api/v1/cv/{cvDocument}/apply`
- **THEN** the system SHALL return HTTP 404

### Requirement: Import is idempotent
The system SHALL reject duplicate import attempts.

#### Scenario: Duplicate idempotency key
- **GIVEN** the candidate has already applied an import with idempotency key `abc-123`
- **WHEN** they call POST `/api/v1/cv/{cvDocument}/apply` with the same `Idempotency-Key: abc-123`
- **THEN** the system SHALL return HTTP 409 with `import_already_applied` code
- **AND** the profile SHALL NOT be modified again

#### Scenario: Duplicate import without idempotency key
- **GIVEN** the candidate has already imported a CV document
- **WHEN** they call POST `/api/v1/cv/{cvDocument}/apply` again
- **THEN** the system SHALL return HTTP 409 with `import_already_applied` code

### Requirement: Import validates concurrency
The system SHALL detect and reject concurrent profile modifications.

#### Scenario: Stale profile during import
- **GIVEN** the candidate's profile was modified after the CV was uploaded
- **WHEN** the import detects `updated_at` mismatch
- **THEN** the system SHALL return HTTP 409 with `profile_changed` code
- **AND** roll back any partial changes
- **AND** the document SHALL remain in `ready_for_review` state

### Requirement: Import transaction is atomic
The system SHALL roll back all changes if any part of the import fails.

#### Scenario: Transaction rollback on failure
- **GIVEN** the import creates 2 profile items and 1 skill
- **WHEN** the skill creation fails due to a constraint violation
- **THEN** the transaction SHALL roll back
- **AND** no profile items SHALL be created
- **AND** the import batch SHALL have `status: failed`
- **AND** the document SHALL remain in `ready_for_review` state

### Requirement: Import result is viewable
The system SHALL return the import result after a successful or failed import.

#### Scenario: View successful import result
- **GIVEN** the candidate has applied an import
- **WHEN** they call GET `/api/v1/cv/{cvDocument}/import-result`
- **THEN** the system SHALL return HTTP 200 with the import batch resource showing created, updated, and skipped counts

### Requirement: Profile completion after import
The system SHALL recalculate profile completion after a successful import.

#### Scenario: Profile completion increases
- **GIVEN** the candidate's profile compleetion was 40% before import
- **WHEN** the import adds headline, summary, and an experience item
- **THEN** the profile completion SHALL increase accordingly
- **AND** skills SHALL NOT affect the completion percentage

#### Scenario: Skills excluded from completion
- **GIVEN** the import adds 5 skills to the profile
- **WHEN** the completion is recalculated
- **THEN** the completion percentage SHALL be unchanged by the skills alone

### Requirement: Accepted values are revalidated at import time
The system SHALL revalidate all accepted suggestion values before applying.

#### Scenario: Revalidate accepted value
- **GIVEN** a suggestion was accepted with an edited value
- **WHEN** the import begins
- **THEN** the system SHALL validate the edited value against the profile field's rules (max length, format, etc.)
- **AND** if invalid, SHALL fail the import with details
