## Purpose

Define the ingestion pipeline for creating a job opportunity from a pasted description through AI extraction, skill resolution, and candidate review, ending with an atomic confirmation that produces a trusted opportunity.

## Requirements

### Requirement: Create ingestion with description
The system SHALL accept a pasted job description and create an ingestion record.

#### Scenario: Valid description ingestion
- **GIVEN** an authenticated candidate
- **WHEN** they POST to `/api/v1/opportunities/ingestions` with a `source_description` of at least 50 characters
- **THEN** the system SHALL return 201 with an `IngestionResource` in `draft` status
- **AND** the description SHALL be stored in `source_description`
- **AND** a SHA-256 `content_hash` SHALL be computed from the normalized description

#### Scenario: Description too short
- **GIVEN** an authenticated candidate
- **WHEN** they POST with a `source_description` shorter than 50 characters
- **THEN** the system SHALL return 422 with code `description_too_short`

#### Scenario: Description too long
- **GIVEN** an authenticated candidate
- **WHEN** they POST with a `source_description` exceeding the configured maximum (100000 characters)
- **THEN** the system SHALL return 422 with code `description_too_large`

#### Scenario: Description required
- **GIVEN** an authenticated candidate
- **WHEN** they POST without a `source_description` or with whitespace-only content
- **THEN** the system SHALL return 422 with code `description_required`

#### Scenario: Optional source URL
- **GIVEN** an authenticated candidate
- **WHEN** they POST with an optional `source_url` that is a valid HTTP or HTTPS URL
- **THEN** the system SHALL store the URL as metadata

#### Scenario: Invalid source URL
- **GIVEN** an authenticated candidate
- **WHEN** they POST with a `source_url` that is not HTTP or HTTPS
- **THEN** the system SHALL return 422 with code `invalid_source_url`

#### Scenario: Optional personal label
- **GIVEN** an authenticated candidate
- **WHEN** they POST with an optional `personal_label` within 255 characters
- **THEN** the system SHALL store the label

#### Scenario: Unauthenticated ingestion creation
- **GIVEN** no authenticated session
- **WHEN** they POST to the ingestion endpoint
- **THEN** the system SHALL return 401 with code `unauthenticated`

### Requirement: Duplicate ingestion detection
The system SHALL detect duplicate job descriptions for the same candidate.

#### Scenario: Exact duplicate description
- **GIVEN** an authenticated candidate who already has an ingestion with the same `content_hash`
- **WHEN** they POST the same description
- **THEN** the system SHALL return 409 with code `duplicate_ingestion`
- **AND** the response SHALL include the existing ingestion ID

#### Scenario: Exact duplicate confirmed opportunity
- **GIVEN** an authenticated candidate who already confirmed an opportunity from the same description
- **WHEN** they POST the same description
- **THEN** the system SHALL return 409 with code `duplicate_ingestion`
- **AND** the response SHALL include the existing confirmed opportunity ID

#### Scenario: Exact duplicate cancelled ingestion
- **GIVEN** an authenticated candidate who previously cancelled an ingestion with the same `content_hash`
- **WHEN** they POST the same description
- **THEN** the system SHALL return 409 with code `duplicate_ingestion`
- **AND** the response SHALL include the existing ingestion ID and `cancelled` status
- **AND** no database exception details SHALL be returned

#### Scenario: Different candidate, same description
- **GIVEN** two different authenticated candidates
- **WHEN** candidate B posts the same description as candidate A
- **THEN** the system SHALL create a new ingestion for candidate B (no duplicate)

### Requirement: Ingestion state machine
The system SHALL enforce valid state transitions on ingestions.

#### Scenario: Draft to queued
- **GIVEN** an ingestion in `draft` status
- **WHEN** the system dispatches the processing job
- **THEN** the status SHALL transition to `queued`

#### Scenario: Queued to processing
- **GIVEN** an ingestion in `queued` status
- **WHEN** the worker starts processing
- **THEN** the status SHALL transition to `processing`

#### Scenario: Processing to review_ready
- **GIVEN** an ingestion in `processing` status
- **WHEN** AI extraction completes and suggestions are persisted
- **THEN** the status SHALL transition to `review_ready`

#### Scenario: Processing to failed (retryable)
- **GIVEN** an ingestion in `processing` status
- **WHEN** AI extraction fails due to a transient provider error
- **THEN** the status SHALL transition to `failed`
- **AND** `failure_code` SHALL indicate a retryable error

#### Scenario: Processing to failed (permanent)
- **GIVEN** an ingestion in `processing` status
- **WHEN** AI extraction fails due to schema validation rejection
- **THEN** the status SHALL transition to `failed`
- **AND** `failure_code` SHALL indicate a permanent error
- **AND** retry SHALL be blocked

#### Scenario: Failed to queued (retry)
- **GIVEN** an ingestion in `failed` status with a retryable failure
- **WHEN** the candidate requests retry
- **THEN** the status SHALL transition to `queued`
- **AND** the processing job SHALL be dispatched again

#### Scenario: Review ready to confirmed
- **GIVEN** an ingestion in `review_ready` status
- **WHEN** the candidate confirms with a fresh preview
- **THEN** the status SHALL transition to `confirmed`
- **AND** `confirmed_at` SHALL be set

#### Scenario: Review ready to queued (re-extract)
- **GIVEN** an ingestion in `review_ready` status
- **WHEN** the candidate requests retry
- **THEN** the status SHALL transition to `queued`
- **AND** the extraction SHALL run again with fresh suggestions

#### Scenario: Cancel any non-terminal state
- **GIVEN** an ingestion in `draft`, `queued`, `processing`, `review_ready`, or `failed` status
- **WHEN** the candidate cancels
- **THEN** the status SHALL transition to `cancelled`

#### Scenario: Confirmed cannot be modified
- **GIVEN** an ingestion in `confirmed` status
- **WHEN** the candidate attempts to retry or cancel
- **THEN** the system SHALL return 409 with code `already_confirmed`

#### Scenario: Reanalyze a cancelled ingestion
- **GIVEN** a candidate-owned ingestion in `cancelled` status
- **WHEN** the candidate requests reanalysis
- **THEN** the same ingestion SHALL transition to `queued`
- **AND** stale suggestions and failure state SHALL be cleared
- **AND** the ingestion version and retry count SHALL be incremented
- **AND** extraction SHALL be dispatched for the new ingestion version
- **AND** no duplicate ingestion SHALL be created

#### Scenario: Reject reanalysis outside cancelled state
- **GIVEN** an ingestion that is not `cancelled`
- **WHEN** the candidate requests reanalysis
- **THEN** the system SHALL return 409 with code `ingestion_not_cancelled`

#### Scenario: Ignore work from an earlier analysis attempt
- **GIVEN** a queued or running job for an earlier ingestion version
- **WHEN** the ingestion is cancelled and reanalysis starts a newer version
- **THEN** the earlier job SHALL exit without persisting suggestions or changing status

### Requirement: AI extraction
The system SHALL extract structured job information using an AI provider.

#### Scenario: Successful AI extraction
- **GIVEN** a validated job description
- **WHEN** the AI extraction completes
- **THEN** the system SHALL create suggestions for all supported fields found in the description
- **AND** each suggestion SHALL include the extracted value and source evidence
- **AND** empty or absent fields SHALL produce no suggestion

#### Scenario: Schema validation failure
- **GIVEN** an AI provider response
- **WHEN** the response does not match the `JobAnalysisSchema`
- **THEN** the system SHALL mark the ingestion as `failed` with code `invalid_ai_output`
- **AND** SHALL NOT persist any partial suggestions

#### Scenario: Malformed provider response
- **GIVEN** an AI provider response
- **WHEN** the response cannot be parsed as valid structured output
- **THEN** the system SHALL mark the ingestion as `failed` with code `invalid_ai_output`

#### Scenario: Provider timeout
- **GIVEN** an AI provider call
- **WHEN** the provider does not respond within the configured timeout
- **THEN** the system SHALL mark the processing run as failed with retryable code `provider_unavailable`

#### Scenario: Prompt injection protection
- **GIVEN** a job description containing instructions to ignore extraction rules
- **WHEN** the AI processes the prompt
- **THEN** the system SHALL delimit the description with `<job_description>` tags
- **AND** the prompt SHALL instruct the AI to ignore embedded instructions

### Requirement: Skill resolution
The system SHALL resolve extracted skill labels against the canonical skill catalog.

#### Scenario: Exact skill match
- **GIVEN** a skill suggestion with label "PHP"
- **WHEN** `skills.normalized_name` contains "php"
- **THEN** the suggestion SHALL be marked with resolution `exact`
- **AND** the `skill_id` SHALL reference the canonical skill

#### Scenario: Alias resolution
- **GIVEN** a skill suggestion with label "JS"
- **WHEN** `skill_aliases` maps "js" to the canonical skill "JavaScript"
- **THEN** the suggestion SHALL be marked with resolution `alias`
- **AND** the `skill_id` SHALL reference the canonical skill

#### Scenario: Ambiguous skill
- **GIVEN** a skill suggestion with label "Spring"
- **WHEN** multiple canonical skills match "spring" (e.g., "Spring Framework", "Spring Boot")
- **THEN** the suggestion SHALL be marked with resolution `ambiguous`
- **AND** the candidate SHALL be prompted to choose during review

#### Scenario: Unknown skill
- **GIVEN** a skill suggestion with a label matching no canonical skill or alias
- **WHEN** the label cannot be resolved
- **THEN** the suggestion SHALL be marked with resolution `unknown`
- **AND** the original label SHALL be preserved

#### Scenario: Duplicate skill merge
- **GIVEN** two suggestions with the same resolved skill in the same classification
- **WHEN** both are in `required_skills` group
- **THEN** they SHALL be merged into one suggestion
- **AND** source evidence from both mentions SHALL be preserved

#### Scenario: Cross-classification separation
- **GIVEN** the same skill in both `required_skills` and `preferred_skills`
- **WHEN** resolving
- **THEN** they SHALL remain as separate suggestions
- **AND** the classification SHALL be preserved

### Requirement: Suggestion review
The system SHALL allow the candidate to review and make decisions on each suggestion.

#### Scenario: Accept suggestion
- **GIVEN** a suggestion with a pending review
- **WHEN** the candidate accepts it
- **THEN** the `review_decision` SHALL become `accepted`
- **AND** the `reviewed_at` SHALL be set

#### Scenario: Edit and accept
- **GIVEN** a suggestion with a pending review
- **WHEN** the candidate edits the value and accepts
- **THEN** the `review_decision` SHALL become `edited`
- **AND** the `edited_value` SHALL store the modified value

#### Scenario: Reject suggestion
- **GIVEN** a suggestion
- **WHEN** the candidate rejects it
- **THEN** the `review_decision` SHALL become `rejected`

#### Scenario: Keep blank
- **GIVEN** a nullable field suggestion
- **WHEN** the candidate explicitly keeps it blank
- **THEN** the `review_decision` SHALL become `keep_blank`

#### Scenario: Batch save skills
- **GIVEN** multiple skill suggestions in the same group
- **WHEN** the candidate makes changes to several skills
- **THEN** the system SHALL accept a batch of decisions in one request
- **AND** save all decisions atomically

#### Scenario: Optimistic concurrency
- **GIVEN** a suggestion with `version = 5`
- **WHEN** the candidate sends a decision with `version = 5`
- **THEN** the system SHALL save and increment version to 6
- **WHEN** another request arrives with `version = 5`
- **THEN** the system SHALL return 409 with code `stale_mutation`

### Requirement: Preview
The system SHALL generate a server-side preview of the confirmed opportunity.

#### Scenario: Generate fresh preview
- **GIVEN** an ingestion with all suggestions reviewed
- **WHEN** the candidate requests a preview
- **THEN** the system SHALL return a `PreviewResource` with all fields, responsibilities, skills, requirements, and excluded items
- **AND** include a preview version token
- **AND** NOT create any confirmed records

#### Scenario: Incomplete review rejection
- **GIVEN** an ingestion with pending suggestions
- **WHEN** the candidate requests a preview
- **THEN** the system SHALL return 409 with code `incomplete_review`

#### Scenario: Unresolved conflict rejection
- **GIVEN** an ingestion with ambiguous skills not yet resolved
- **WHEN** the candidate requests a preview
- **THEN** the system SHALL return 409 with code `unresolved_skill_mapping`

#### Scenario: Stale preview
- **GIVEN** a previously generated preview
- **WHEN** the candidate changes a decision after generating the preview
- **THEN** the preview SHALL become stale
- **AND** the system SHALL reject confirmation with code `stale_preview`

### Requirement: Confirmation
The system SHALL create a trusted job opportunity atomically.

#### Scenario: Successful confirmation
- **GIVEN** an ingestion in `review_ready` status with all decisions made and a fresh preview
- **WHEN** the candidate confirms
- **THEN** the system SHALL create a `job_opportunities` record with all confirmed fields
- **AND** create `job_requirements` for responsibilities, experience, education, languages, certifications
- **AND** create `job_opportunity_skills` for required and preferred skills
- **AND** set the ingestion to `confirmed` with `confirmed_at`
- **AND** return the `OpportunityResource` with status 200

#### Scenario: Idempotent confirmation
- **GIVEN** a confirmed ingestion
- **WHEN** the candidate confirms again
- **THEN** the system SHALL return the existing confirmed opportunity
- **AND** SHALL NOT create a duplicate

#### Scenario: Confirmation with incomplete decisions
- **GIVEN** an ingestion with pending suggestions
- **WHEN** the candidate attempts to confirm
- **THEN** the system SHALL return 409 with code `incomplete_review`

#### Scenario: Confirmation with unresolved ambiguous skills
- **GIVEN** an ingestion with ambiguous skills
- **WHEN** the candidate attempts to confirm without resolving
- **THEN** the system SHALL return 409 with code `unresolved_skill_mapping`

#### Scenario: Confirmation with stale preview
- **GIVEN** an ingestion with a stale preview version
- **WHEN** the candidate attempts to confirm
- **THEN** the system SHALL return 409 with code `stale_preview`

#### Scenario: Confirmation for non-review-ready ingestion
- **GIVEN** an ingestion in `draft`, `queued`, `processing`, or `failed` status
- **WHEN** the candidate attempts to confirm
- **THEN** the system SHALL return 409 with code `ingestion_not_reviewable`

#### Scenario: Transaction rollback on failure
- **GIVEN** a confirmation request
- **WHEN** any step of the confirmation transaction fails
- **THEN** the entire transaction SHALL be rolled back
- **AND** the ingestion SHALL remain in `review_ready` status
- **AND** no partial data SHALL be persisted

### Requirement: Cancel and delete
The system SHALL allow the candidate to cancel or delete an unconfirmed ingestion.

#### Scenario: Cancel unconfirmed ingestion
- **GIVEN** an ingestion that is not `confirmed` or `cancelled`
- **WHEN** the candidate cancels
- **THEN** the status SHALL transition to `cancelled`
- **AND** suggestions SHALL remain for audit

#### Scenario: Delete unconfirmed ingestion
- **GIVEN** an ingestion that is not `confirmed`
- **WHEN** the candidate deletes
- **THEN** the ingestion and its suggestions SHALL be hard deleted
- **AND** no confirmed data is lost

#### Scenario: Cannot delete confirmed
- **GIVEN** a confirmed ingestion
- **WHEN** the candidate attempts to delete
- **THEN** the system SHALL return 409 with code `already_confirmed`

### Requirement: View confirmed opportunity
The system SHALL display a confirmed opportunity in readonly mode.

#### Scenario: View confirmed opportunity
- **GIVEN** a confirmed opportunity owned by the candidate
- **WHEN** they request it
- **THEN** the system SHALL return the `OpportunityResource` with all structured data

#### Scenario: Cross-user access to confirmed opportunity
- **GIVEN** a confirmed opportunity owned by candidate A
- **WHEN** candidate B requests it
- **THEN** the system SHALL return 404

### Requirement: List opportunities
The system SHALL list all opportunities and ingestions for the candidate.

#### Scenario: List with mixed states
- **GIVEN** an authenticated candidate with confirmed opportunities and active ingestions
- **WHEN** they request the list
- **THEN** the system SHALL return both confirmed and unconfirmed items
- **AND** each item SHALL show its status and next action
- **AND** results SHALL be paginated

### Requirement: Authorization
The system SHALL enforce ownership on all resources.

#### Scenario: Cross-user ingestion access
- **GIVEN** an ingestion owned by candidate A
- **WHEN** candidate B requests it by ID
- **THEN** the system SHALL return 404

#### Scenario: Cross-user suggestion access
- **GIVEN** suggestions belonging to candidate A's ingestion
- **WHEN** candidate B requests them
- **THEN** the system SHALL return 404

#### Scenario: Cross-user preview access
- **GIVEN** an ingestion owned by candidate A
- **WHEN** candidate B requests a preview
- **THEN** the system SHALL return 404
