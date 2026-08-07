## ADDED Requirements

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
