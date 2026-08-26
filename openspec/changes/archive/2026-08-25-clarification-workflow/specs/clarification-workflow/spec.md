## ADDED Requirements

### Requirement: CLAR-001 — Clarification session from uncertain findings
The system SHALL generate a clarification question session for a completed match analysis that exposes high-impact uncertain findings. A finding SHALL be eligible when it has match state `partial`, `gap`, or `unknown` with a factor of 0.00 or 0.50, the source requirement is required or preferred, no trusted answer already exists for it, and no eligible question already exists for the same finding in an open session.

#### Scenario: Eligible partial finding generates a question
- **GIVEN** a completed analysis with a required-skill finding in `partial` state with factor 0.50 (claimed skill without evidence)
- **WHEN** the candidate requests the clarification session for the analysis
- **THEN** the session SHALL include a question for that finding
- **AND** the question SHALL reference the finding, its requirement, and the evidence basis

#### Scenario: Eligible gap finding generates a question
- **GIVEN** a completed analysis with a required-skill finding in `gap` state with factor 0.00 and no candidate skill record
- **WHEN** the candidate requests the clarification session
- **THEN** the session SHALL include a question for that finding

#### Scenario: Eligible unknown finding generates a question
- **GIVEN** a completed analysis with a required-skill finding in `unknown` state and a resolvable category
- **WHEN** the candidate requests the clarification session
- **THEN** the session SHALL include a question for that finding
- **AND** the question SHALL be created from the gap template for the finding's category

#### Scenario: Verified skill never generates a question
- **GIVEN** a finding in `matched` state for a verified skill
- **WHEN** the candidate requests the clarification session
- **THEN** the session SHALL NOT include a question for that finding

#### Scenario: Low-impact gap never generates a question
- **GIVEN** a preferred-skill gap or unknown the engine classifies as low-impact (below the impact threshold)
- **WHEN** the candidate requests the clarification session
- **THEN** the session SHALL NOT include a question for that finding

#### Scenario: Rejected or archived skill never generates a question
- **GIVEN** a finding whose candidate skill is in `rejected` or `archived` state
- **WHEN** the candidate requests the clarification session
- **THEN** the session SHALL NOT include a question for that finding

#### Scenario: Unclassified finding never generates a question
- **GIVEN** an unknown finding whose category resolves to no template
- **WHEN** the candidate requests the clarification session
- **THEN** the session SHALL NOT include a question for that finding

#### Scenario: Session is capped
- **GIVEN** an analysis with more than three eligible findings
- **WHEN** the session is built
- **THEN** the session SHALL contain at most three questions
- **AND** the questions SHALL be ordered by impact (importance, then factor gap)

#### Scenario: No eligible findings yields an empty session
- **GIVEN** an analysis with no eligible findings
- **WHEN** the candidate requests the clarification session
- **THEN** the session SHALL be empty
- **AND** the UI SHALL show the empty state instead of questions

### Requirement: CLAR-002 — Deterministic-template question generation
The system SHALL compose question text, type, and options from a versioned registry of deterministic templates keyed by finding type and requirement importance. The assistant SHALL NOT create, remove, or change eligibility of questions.

#### Scenario: Template drives question type and prompt
- **GIVEN** an eligible skill-missing finding for a required requirement
- **WHEN** the question is generated
- **THEN** the question SHALL be created from the matching versioned template with a stable `template_key`
- **AND** SHALL have a question type from the approved set (yes_no, yes_no_with_details, text, select, number)

#### Scenario: No duplicate questions for one finding
- **GIVEN** an eligible finding with an existing open question
- **WHEN** the session is built
- **THEN** the session SHALL NOT create a duplicate question for that finding
- **AND** the duplicate-clarification rate SHALL remain below 5 percent

#### Scenario: AI ranks and rewrites eligible questions only
- **GIVEN** a deterministic question set and the clarification assistant available
- **WHEN** the assistant returns a schema-validated ranking and reworded prompts
- **THEN** the session SHALL use the ranked order and reworded prompts
- **AND** SHALL record assistant provenance (prompt version, provider, model) in question AI metadata
- **AND** SHALL NOT allow the assistant to add or remove a question

#### Scenario: Assistant failure falls back to deterministic set
- **GIVEN** the assistant returns malformed output or the provider fails
- **WHEN** the session is built
- **THEN** the session SHALL use the deterministic questions unchanged
- **AND** SHALL record the fallback reason in AI metadata
- **AND** SHALL NOT persist a partial or modified question set

### Requirement: CLAR-003 — One answer per question with explicit lifecycle
The system SHALL persist answers as candidate input with an explicit status and SHALL enforce one answer per clarification question.

#### Scenario: Answer stored as pending input
- **GIVEN** an open question owned by the candidate
- **WHEN** they submit an answer
- **THEN** the system SHALL store the answer with status `pending`
- **AND** SHALL link it to the question, the finding, and the analysis

#### Scenario: Duplicate answer rejected
- **GIVEN** an existing answer for a question
- **WHEN** the candidate submits another answer
- **THEN** the system SHALL NOT create a second answer
- **AND** SHALL return the existing answer or a stable conflict code

#### Scenario: Question skipped
- **GIVEN** an open question
- **WHEN** the candidate skips it
- **THEN** the question SHALL move to `skipped` state
- **AND** no proposal or mutation SHALL be created

#### Scenario: Answer status transitions after review
- **GIVEN** a pending answer
- **WHEN** the candidate accepts or rejects its proposal
- **THEN** the answer SHALL move to `accepted` or `rejected` accordingly
- **AND** an expired question SHALL be marked `expired` and excluded from new sessions

### Requirement: CLAR-004 — Trust boundary: mutation only after explicit acceptance
The system SHALL treat answers as candidate input and SHALL NEVER mutate trusted profile data until the candidate explicitly accepts a concrete proposal. Proposals SHALL describe the target entity, field, before value, after value, and origin answer.

#### Scenario: Accepted evidence-bearing answer verifies a skill
- **GIVEN** a `yes` answer with evidence for a missing required skill
- **WHEN** the candidate accepts the proposal
- **THEN** the system SHALL promote the candidate skill to `verified` with the evidence and the origin answer id recorded
- **AND** the accepted proposal SHALL become immutable

#### Scenario: Pending answer never mutates profile data
- **GIVEN** a pending answer that the candidate has not reviewed
- **WHEN** any request other than review runs
- **THEN** the system SHALL NOT change the candidate skill state or profile

#### Scenario: Rejected proposal never mutates profile data
- **GIVEN** a proposal the candidate rejects
- **WHEN** the rejection is persisted
- **THEN** the system SHALL NOT change the candidate skill state or profile
- **AND** the answer SHALL be recorded as rejected

### Requirement: CLAR-005 — Evidence or explicit no-evidence acknowledgement
The system SHALL require evidence or an explicit no-evidence acknowledgement before any profile-changing answer becomes trusted, and SHALL NEVER silently verify a missing skill.

#### Scenario: Yes without evidence requires acknowledgement
- **GIVEN** a `yes` answer with no evidence
- **WHEN** the proposal is reviewed
- **THEN** the system SHALL require an explicit `acknowledged_no_evidence` confirmation
- **AND** on acceptance SHALL set the skill to `claimed` (factor 0.50, shown separately)
- **AND** SHALL NOT set the skill to `verified`

#### Scenario: No answer rejects the skill
- **GIVEN** a `no` answer for a missing required skill
- **WHEN** the candidate accepts the proposal
- **THEN** the system SHALL mark the skill as `rejected` (or record the acknowledged gap)
- **AND** the engine SHALL stop generating clarification questions for it

#### Scenario: No evidence means no fabrication
- **GIVEN** a candidate who does not confirm possession of a missing skill
- **WHEN** no answer and no evidence exist
- **THEN** the skill SHALL remain missing or uncertain
- **AND** SHALL NOT appear as a professional claim

### Requirement: CLAR-006 — Accepted changes mark the analysis stale
The system SHALL mark the owning match analysis stale through the fingerprint service after an accepted profile-changing proposal and SHALL NOT recompute the score in the same transaction.

#### Scenario: Accepted proposal triggers staleness
- **GIVEN** an accepted evidence-bearing proposal that changes a trusted skill
- **WHEN** the proposal is applied
- **THEN** the owning analysis SHALL be marked stale via fingerprint change
- **AND** the old score SHALL remain readable until recalculation

#### Scenario: Score never recomputed during mutation
- **GIVEN** a trusted profile mutation applied from an accepted proposal
- **WHEN** the mutation transaction commits
- **THEN** the system SHALL NOT compute or write a new score in that transaction
- **AND** staleness marking SHALL be dispatched after commit

#### Scenario: Stale analysis offers recalculation
- **GIVEN** an analysis marked stale by an accepted clarification
- **WHEN** the candidate views the brief
- **THEN** the brief SHALL show the stale notice and a recalculate action

### Requirement: CLAR-007 — Clarification API with ownership scoping
The system SHALL expose the clarification endpoints under `/api/v1` with policies on every lookup, RFC 9457 problem details, and rate limiting.

#### Scenario: List questions for own analysis
- **GIVEN** a completed analysis owned by the candidate with an open session
- **WHEN** they GET `/api/v1/matches/{id}/clarifications`
- **THEN** the system SHALL return the open questions with their types, options, and evidence basis

#### Scenario: Answer own question
- **GIVEN** an open question owned by the candidate
- **WHEN** they POST `/api/v1/clarifications/{id}/answer`
- **THEN** the system SHALL create the pending answer and return 201

#### Scenario: Review own answer
- **GIVEN** a pending answer owned by the candidate
- **WHEN** they POST `/api/v1/clarifications/{id}/review`
- **THEN** the system SHALL apply the accepted proposal transactionally or return the proposal for rejection

#### Scenario: Cross-user question denied
- **GIVEN** a clarification question owned by a different candidate
- **WHEN** they attempt to read, answer, or review it
- **THEN** the system SHALL return 404 with a stable problem code
- **AND** SHALL NOT reveal that the question exists

#### Scenario: Question does not exist
- **GIVEN** no clarification question with the given id
- **WHEN** they attempt to answer or review it
- **THEN** the system SHALL return 404 with a stable problem code

#### Scenario: Expired session rejected
- **GIVEN** a question in `expired` state
- **WHEN** they attempt to answer it
- **THEN** the system SHALL return 409 with a stable problem code
- **AND** SHALL NOT persist the answer

#### Scenario: Unauthenticated access denied
- **GIVEN** no authenticated session
- **WHEN** they access a clarification endpoint
- **THEN** the system SHALL return 401 with code `unauthenticated`

#### Scenario: Rate limit exceeded on writes
- **GIVEN** a candidate who exceeded the general write rate limit
- **WHEN** they POST an answer or review
- **THEN** the system SHALL return 429 with a stable problem code

### Requirement: CLAR-008 — Privacy, security, and safe failure
The system SHALL keep clarification processing private and safe: the assistant receives only minimal delimited candidate-owned context, external content is treated as untrusted data, no partial trusted update is ever persisted on failure, and accepted proposals are audited.

#### Scenario: Minimal assistant context
- **GIVEN** the clarification assistant runs
- **THEN** its prompt SHALL contain only the finding, requirement label, and evidence basis as delimited untrusted content
- **AND** SHALL NOT contain passwords, cookies, tokens, or unrelated profile data

#### Scenario: Provider failure is safe
- **GIVEN** a provider failure during assistant ranking
- **WHEN** the session is built
- **THEN** the deterministic question set SHALL be used
- **AND** SHALL NOT persist a partial or modified question set

#### Scenario: Proposal acceptance is audited
- **GIVEN** an accepted proposal
- **WHEN** the proposal is persisted
- **THEN** an audit event SHALL record the origin answer, target entity, before value, and after value
- **AND** the accepted proposal SHALL be immutable

### Requirement: CLAR-009 — Generate a clarification session through the API
The system SHALL expose an explicit generate endpoint that builds the clarification session for a completed analysis on demand, SHALL allow only the analysis owner to generate, SHALL cap and deduplicate per finding, and SHALL apply write rate limiting.

#### Scenario: Generate questions for own analysis
- **GIVEN** a completed analysis owned by the candidate with eligible uncertain findings
- **WHEN** they POST `/api/v1/matches/{id}/clarifications`
- **THEN** the system SHALL create pending questions from the deterministic templates (and validated assistant reword/rank when enabled)
- **AND** SHALL return the session resource with the questions and progress

#### Scenario: Regenerate never duplicates a question
- **GIVEN** an open question already exists for a finding in the same analysis
- **WHEN** the candidate generates the session again
- **THEN** the system SHALL NOT create a second question for that finding
- **AND** SHALL return the existing open session

#### Scenario: No eligible findings yields an empty session
- **GIVEN** an analysis with no eligible findings
- **WHEN** the candidate generates the session
- **THEN** the system SHALL return an empty session
- **AND** the UI SHALL show the empty state

#### Scenario: Cross-user generate denied
- **GIVEN** a completed analysis owned by a different candidate
- **WHEN** they attempt to generate its clarification session
- **THEN** the system SHALL return 404 with a stable problem code
- **AND** SHALL NOT reveal that the analysis exists

#### Scenario: Generate requires authentication
- **GIVEN** no authenticated session
- **WHEN** they access the generate endpoint
- **THEN** the system SHALL return 401 with code `unauthenticated`

#### Scenario: Generate is rate limited
- **GIVEN** a candidate who exceeded the clarification generate rate limit
- **WHEN** they POST the generate endpoint
- **THEN** the system SHALL return 429 with a stable problem code

### Requirement: CLAR-010 — Session generation signal
The system SHALL expose `generable_count` on the clarification session resource so clients can decide whether to offer a generate action without calling it first. The count SHALL equal the number of questions a generate call could still create and SHALL be computed from eligible findings not already covered by an open question in the session.

#### Scenario: Session exposes how many more questions could be generated
- **GIVEN** a completed analysis with eligible findings and no open questions
- **WHEN** the candidate fetches the clarification session
- **THEN** the session SHALL include `generable_count` equal to the number of eligible findings (up to the cap)
- **AND** a generate call SHALL create exactly that many questions

#### Scenario: Generate pass drops the count to zero
- **GIVEN** a session where the candidate generated questions for every eligible finding
- **WHEN** the candidate fetches the session again
- **THEN** `generable_count` SHALL be 0
- **AND** the UI SHALL NOT offer a generate action

#### Scenario: Count reflects only genuinely eligible findings
- **GIVEN** an analysis whose eligible findings already have open questions or are low-impact
- **WHEN** the count is computed
- **THEN** `generable_count` SHALL exclude those findings
