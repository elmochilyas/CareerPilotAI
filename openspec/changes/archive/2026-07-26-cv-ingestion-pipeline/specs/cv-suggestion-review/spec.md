## ADDED Requirements

### Requirement: Candidate reviews suggestions individually
The system SHALL present each extracted suggestion for individual review with current profile comparison.

#### Scenario: List suggestions for review
- **GIVEN** a CV document with status `ready_for_review` has 8 suggestions
- **WHEN** the candidate calls GET `/api/v1/cv/{cvDocument}/suggestions`
- **THEN** the system SHALL return HTTP 200 with 8 `CvSuggestionResource` items
- **AND** each suggestion SHALL include `current_value` (existing profile snapshot) and `suggested_value` (extracted value)

#### Scenario: Cross-user suggestion access returns 404
- **GIVEN** candidate A has suggestions for their CV
- **WHEN** candidate B calls GET `/api/v1/cv/{cvDocument}/suggestions`
- **THEN** the system SHALL return HTTP 404

### Requirement: Candidate accepts a suggestion
The system SHALL record when a candidate accepts a suggestion as-is.

#### Scenario: Accept headline suggestion
- **GIVEN** a headline suggestion with `review_status: pending`
- **WHEN** the candidate sends `{"decision": "accepted"}` to PATCH `/api/v1/cv/{cvDocument}/suggestions/{cvSuggestion}`
- **THEN** the system SHALL set `review_status` to `accepted`
- **AND** return HTTP 200 with the updated suggestion

### Requirement: Candidate edits and accepts a suggestion
The system SHALL allow the candidate to modify a suggested value before accepting.

#### Scenario: Edit summary suggestion
- **GIVEN** a professional_summary suggestion
- **WHEN** the candidate sends `{"decision": "edited", "edited_value": {"text": "Corrected summary text"}}`
- **THEN** the system SHALL set `review_status` to `edited`
- **AND** store the edited value in `reviewed_decision`
- **AND** return HTTP 200

### Requirement: Candidate rejects a suggestion
The system SHALL record when a candidate rejects a suggestion.

#### Scenario: Reject a duplicate experience
- **GIVEN** an experience suggestion that duplicates existing profile data
- **WHEN** the candidate sends `{"decision": "rejected"}`
- **THEN** the system SHALL set `review_status` to `rejected`
- **AND** the suggestion SHALL NOT be included in the import

### Requirement: Candidate keeps existing profile value
The system SHALL allow the candidate to keep their current profile value instead of using the extracted one.

#### Scenario: Keep existing headline
- **GIVEN** a headline suggestion with a different extracted value
- **WHEN** the candidate sends `{"decision": "keep_existing"}`
- **THEN** the system SHALL set `review_status` to `keep_existing`
- **AND** the existing profile value SHALL be preserved during import

### Requirement: Candidate can create new or update existing items
The system SHALL support creating new profile items or updating matching existing ones.

#### Scenario: Create new experience
- **GIVEN** a new experience suggestion that does not match any existing profile item
- **WHEN** the candidate sends `{"decision": "accepted", "action": "create_new"}`
- **THEN** the suggestion SHALL be included as a new profile item creation during import

#### Scenario: Update existing experience
- **GIVEN** an experience suggestion matching an existing profile item
- **WHEN** the candidate sends `{"decision": "accepted", "action": "update_existing", "target_id": 42}`
- **THEN** the suggestion SHALL update the existing profile item during import

### Requirement: Candidate saves batch decisions
The system SHALL accept multiple review decisions in a single request.

#### Scenario: Batch save all decisions
- **GIVEN** the candidate has reviewed 5 suggestions
- **WHEN** they call POST `/api/v1/cv/{cvDocument}/suggestions/batch` with 5 decisions
- **THEN** all 5 suggestions SHALL be updated atomically
- **AND** return HTTP 200 with the updated suggestions

#### Scenario: Batch with one invalid decision
- **GIVEN** the candidate submits 3 decisions where one has an invalid value
- **WHEN** processing the batch
- **THEN** the system SHALL reject all 3 decisions
- **AND** return HTTP 422
- **AND** no suggestion SHALL be updated

### Requirement: Review is not possible after import
The system SHALL prevent modifying review decisions after the import has been applied.

#### Scenario: Modify after import returns 409
- **GIVEN** the candidate has imported a CV
- **WHEN** they attempt to PATCH a suggestion
- **THEN** the system SHALL return HTTP 409

### Requirement: No profile data is modified during review
The system SHALL NOT modify any profile data during the review phase.

#### Scenario: Review does not affect profile
- **GIVEN** the candidate reviews and accepts suggestions
- **WHEN** no import has been applied
- **THEN** the candidate profile SHALL be unchanged
- **AND** the profile completion SHALL be unchanged
- **AND** no skill state SHALL have changed
