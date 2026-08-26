## ADDED Requirements

### Requirement: CLAR-011 - Append-only clarification audit trail
The system SHALL record append-only audit events for the clarification lifecycle. Each event SHALL identify the acting user, the owning match analysis, and where applicable the question, answer, proposal, target type, and target id, plus before/after values for mutations. The system SHALL record events for at least: question created, answer submitted, proposal generated, proposal accepted, proposal rejected, question skipped, and proposal apply failure. Events SHALL be written best-effort so an audit failure never rolls back or masks an already-committed trusted mutation. Audit records SHALL NOT contain raw provider output, prompts, credentials, tokens, or secrets beyond the structured values needed to explain the change.

#### Scenario: Question lifecycle events
- **WHEN** a clarification session generates questions and the candidate answers one
- **THEN** append-only events exist for the question creation and the answer submission

#### Scenario: Proposal decision events
- **WHEN** the candidate accepts or rejects a generated proposal, or skips its question
- **THEN** append-only events exist recording acceptance, rejection, and skip respectively

#### Scenario: Apply failure is audited
- **WHEN** applying an accepted proposal fails validation or targets missing data
- **THEN** an append-only event records the failure without storing provider payloads

#### Scenario: Audit failure never blocks trusted mutation
- **WHEN** writing an audit event throws after a trusted mutation committed
- **THEN** the mutation remains committed and only a warning is logged

### Requirement: CLAR-012 - Trusted-mutation target scope
Only proposal target types with an implemented trusted-apply path SHALL be applicable to trusted profile data. Today that scope is `candidate_skill`. A proposal whose target type has no implemented trusted-apply path SHALL be rejected at apply time with HTTP 422 and problem code `proposal_not_supported`, without partial mutation. Extending apply to additional target types (experience, education, language, basic profile fields) SHALL require showing exactly what will change, explicit candidate approval, preserved source/provenance, concurrency protection, and completion/staleness updates — and is explicitly deferred from Phase A unless already structurally supported.

#### Scenario: Supported target applies through trusted actions
- **WHEN** an accepted `candidate_skill` proposal is applied
- **THEN** the mutation runs through the existing skill domain actions inside one transaction

#### Scenario: Unsupported target rejected safely
- **WHEN** an accepted proposal carries a target type without an implemented trusted-apply path
- **THEN** the system returns HTTP 422 `proposal_not_supported`, changes no trusted data, and records the failure audit event

#### Scenario: AI answers never mutate directly
- **WHEN** a clarification answer is submitted
- **THEN** no trusted profile data changes until the candidate explicitly accepts a generated proposal
