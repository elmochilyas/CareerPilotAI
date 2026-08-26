## Purpose

Define the candidate-facing match API: endpoints for creating, listing, reading, and recalculating match analyses, with ownership authorization, RFC 9457 problem details, rate limiting, asynchronous creation, and a resource shape that exposes category scores, matches, uncertain items, gaps, and suggestions.

## Requirements

### Requirement: Create a match analysis
The system SHALL expose `POST /api/v1/opportunities/{id}/matches` to start a match analysis for a confirmed opportunity owned by the authenticated candidate, returning `202 Accepted` with an operation resource and a polling location.

#### Scenario: Analysis creation accepted
- **GIVEN** an authenticated candidate who owns the confirmed opportunity
- **WHEN** they POST to `/api/v1/opportunities/{id}/matches`
- **THEN** the system SHALL return 202 with an operation resource in `queued` state
- **AND** the response SHALL include the operation ID and a `Location` header for polling
- **AND** an analysis record SHALL be created

#### Scenario: Opportunity not owned
- **GIVEN** an authenticated candidate who does not own the opportunity
- **WHEN** they POST to `/api/v1/opportunities/{id}/matches`
- **THEN** the system SHALL return 404 with a stable problem code
- **AND** SHALL NOT reveal that the opportunity exists

#### Scenario: Opportunity does not exist
- **GIVEN** no opportunity with the given id
- **WHEN** they POST to `/api/v1/opportunities/{id}/matches`
- **THEN** the system SHALL return 404 with a stable problem code

#### Scenario: Unconfirmed opportunity rejected
- **GIVEN** an opportunity that is not in a confirmed state
- **WHEN** they POST to `/api/v1/opportunities/{id}/matches`
- **THEN** the system SHALL return 409 with a stable problem code

#### Scenario: Unauthenticated creation
- **GIVEN** no authenticated session
- **WHEN** they POST to the match creation endpoint
- **THEN** the system SHALL return 401 with code `unauthenticated`

#### Scenario: Idempotent duplicate creation
- **GIVEN** an analysis already queued or processing for the same profile and opportunity
- **WHEN** they POST again with the same idempotency key
- **THEN** the system SHALL return 202 referencing the existing operation
- **AND** SHALL NOT create a duplicate analysis

#### Scenario: Rate limit exceeded on creation
- **GIVEN** a candidate who exceeded the configured match creation limit
- **WHEN** they POST to the match creation endpoint
- **THEN** the system SHALL return 429 with a stable problem code

### Requirement: Poll analysis status
The system SHALL expose the operation resource so the candidate can poll analysis status through `queued`, `processing`, `completed`, and failure states.

#### Scenario: Poll completes
- **GIVEN** an analysis created for the candidate
- **WHEN** they poll the operation endpoint
- **THEN** the response SHALL reflect the current state
- **AND** once processing finishes SHALL return `completed` with the analysis resource

#### Scenario: Poll after failure
- **GIVEN** an analysis that failed during processing
- **WHEN** they poll the operation endpoint
- **THEN** the response SHALL expose a failed state with a stable problem code
- **AND** SHALL NOT include a partial score

### Requirement: List match analyses
The system SHALL expose `GET /api/v1/opportunities/{id}/matches` returning the candidate's analyses for that opportunity, newest first, using cursor pagination, with the latest analysis flagged.

#### Scenario: List analyses
- **GIVEN** an authenticated candidate who owns the opportunity and has analyses
- **WHEN** they GET `/api/v1/opportunities/{id}/matches`
- **THEN** the system SHALL return the analyses newest first with pagination metadata
- **AND** SHALL flag the latest analysis

#### Scenario: List analyses cross-user denied
- **GIVEN** an authenticated candidate who does not own the opportunity
- **WHEN** they GET `/api/v1/opportunities/{id}/matches`
- **THEN** the system SHALL return 404

#### Scenario: List with no analyses
- **GIVEN** an opportunity with no analyses
- **WHEN** they GET `/api/v1/opportunities/{id}/matches`
- **THEN** the system SHALL return an empty paginated result

### Requirement: Read a single match analysis
The system SHALL expose `GET /api/v1/matches/{id}` returning the full analysis with category scores, per-requirement results, evidence references, versions, fingerprints, stale status, and critical-missing warnings.

#### Scenario: Read own analysis
- **GIVEN** a completed analysis owned by the candidate
- **WHEN** they GET `/api/v1/matches/{id}`
- **THEN** the response SHALL include the overall score, category scores, per-requirement results, evidence references, scoring version, fingerprints, and any critical-missing warnings

#### Scenario: Read another candidate's analysis
- **GIVEN** an analysis owned by a different candidate
- **WHEN** they GET `/api/v1/matches/{id}`
- **THEN** the system SHALL return 404 with a stable problem code

#### Scenario: Analysis does not exist
- **GIVEN** no analysis with the given id
- **WHEN** they GET `/api/v1/matches/{id}`
- **THEN** the system SHALL return 404

#### Scenario: Stale analysis flags staleness
- **GIVEN** an analysis whose profile or opportunity changed since completion
- **WHEN** they GET `/api/v1/matches/{id}`
- **THEN** the response SHALL include a stale flag
- **AND** SHALL include the versions that differ from the current sources

### Requirement: Recalculate a match analysis
The system SHALL expose `POST /api/v1/matches/{id}/recalculate` to create a fresh snapshot, returning `202 Accepted` with the new operation.

#### Scenario: Recalculate own analysis
- **GIVEN** an analysis owned by the candidate
- **WHEN** they POST to `/api/v1/matches/{id}/recalculate`
- **THEN** the system SHALL create a new analysis snapshot and return 202 with its operation resource
- **AND** the previous analysis SHALL remain stored unchanged

#### Scenario: Recalculate another candidate's analysis
- **GIVEN** an analysis owned by a different candidate
- **WHEN** they POST to `/api/v1/matches/{id}/recalculate`
- **THEN** the system SHALL return 404

#### Scenario: Recalculate requires no active analysis
- **GIVEN** an analysis already queued or processing for the same profile and opportunity
- **WHEN** they POST to `/api/v1/matches/{id}/recalculate`
- **THEN** the system SHALL return 409 referencing the active analysis
- **AND** SHALL NOT create a duplicate

### Requirement: Problem-details error contract
The API SHALL return RFC 9457-style problem details with stable problem codes and no internal exception messages.

#### Scenario: Validation error shape
- **GIVEN** a request with an invalid parameter
- **WHEN** the system rejects it
- **THEN** the response SHALL include `type`, `title`, `status`, `detail`, `instance`, `code`, `errors`, and `request_id`
- **AND** SHALL NOT expose stack traces or internal messages

#### Scenario: Unknown filter or sort rejected
- **GIVEN** a list request with a filter or sort field outside the allowlist
- **WHEN** the system validates it
- **THEN** the system SHALL return 422 with a validation problem detail

### Requirement: Request ID propagation
The API SHALL accept and generate `X-Request-ID` and propagate it through the analysis job and provider calls.

#### Scenario: Request ID echoed
- **GIVEN** a client sends `X-Request-ID`
- **WHEN** they create a match analysis
- **THEN** the response SHALL include the same request ID
- **AND** the queued job SHALL carry the request ID for logs and metrics

### Requirement: Match analysis resource includes tailoring fields
The `MatchAnalysisResource` SHALL include a `tailorable` boolean field indicating whether the analysis is completed and eligible for CV tailoring. The `MatchFindingResource` SHALL include the `tailoring_relevance` field from the match engine. These fields are additive and do not change existing match endpoint behavior.

#### Scenario: Completed analysis is tailorable
- **WHEN** the match analysis status is `completed`
- **THEN** the resource includes `tailorable = true`

#### Scenario: Incomplete analysis is not tailorable
- **WHEN** the match analysis status is `queued` or `processing`
- **THEN** the resource includes `tailorable = false`

### Requirement: Clarification routes on the match API
The system SHALL expose clarification routes scoped to a match analysis owned by the authenticated candidate: `GET /api/v1/matches/{id}/clarifications` to list the open question session, `POST /api/v1/clarifications/{id}/answer` to create a pending answer, `POST /api/v1/clarifications/{id}/review` to review a pending answer's proposal, and `POST /api/v1/clarifications/{id}/skip` to skip a question.

#### Scenario: List open questions
- **GIVEN** an authenticated candidate who owns the analysis and has an open clarification session
- **WHEN** they GET `/api/v1/matches/{id}/clarifications`
- **THEN** the system SHALL return the open questions with type, options, evidence basis, and progress
- **AND** SHALL return an empty list when the session has no open questions
- **AND** SHALL include `generable_count` on the session resource

#### Scenario: Generate returns the session with the generation signal
- **GIVEN** an authenticated candidate who owns a completed analysis with eligible findings
- **WHEN** they POST `/api/v1/matches/{id}/clarifications`
- **THEN** the system SHALL create the pending questions and return the session resource with progress
- **AND** the session resource SHALL include `generable_count`

#### Scenario: List questions for another candidate's analysis
- **GIVEN** an analysis owned by a different candidate
- **WHEN** they GET `/api/v1/matches/{id}/clarifications`
- **THEN** the system SHALL return 404 with a stable problem code
- **AND** SHALL NOT reveal that the analysis exists

#### Scenario: Answer a question
- **GIVEN** an open clarification question owned by the candidate
- **WHEN** they POST `/api/v1/clarifications/{id}/answer` with a valid payload
- **THEN** the system SHALL create a pending answer and return 201

#### Scenario: Review an answer proposal
- **GIVEN** a pending answer owned by the candidate
- **WHEN** they POST `/api/v1/clarifications/{id}/review` with an accept, edit, or skip decision
- **THEN** the system SHALL apply the accepted proposal transactionally or record the rejection
- **AND** SHALL return the resulting answer state

#### Scenario: Skip a question
- **GIVEN** an open clarification question owned by the candidate
- **WHEN** they POST `/api/v1/clarifications/{id}/skip`
- **THEN** the question SHALL move to skipped state
- **AND** SHALL NOT create an answer or proposal

#### Scenario: Unauthenticated clarification access
- **GIVEN** no authenticated session
- **WHEN** they access any clarification route
- **THEN** the system SHALL return 401 with code `unauthenticated`

#### Scenario: Rate limit exceeded on clarification writes
- **GIVEN** a candidate who exceeded the configured write limit
- **WHEN** they POST an answer, review, or skip
- **THEN** the system SHALL return 429 with a stable problem code
