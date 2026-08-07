## ADDED Requirements

### Requirement: CLAR-EVIDENCE-001 — Clarification answers link to skill evidence
The system SHALL record the link between an accepted clarification answer and the evidence (or explicit no-evidence acknowledgement) that drove a skill state change, so the change remains traceable to the answer, finding, and analysis.

#### Scenario: Accepted answer adds evidence
- **GIVEN** an accepted clarification answer that promoted a skill to `verified` with evidence
- **WHEN** the skill evidence is later inspected
- **THEN** the evidence SHALL reference the origin clarification answer and question

#### Scenario: Accepted no-evidence acknowledgement recorded
- **GIVEN** an accepted clarification answer with `acknowledged_no_evidence`
- **WHEN** the skill state changes to `claimed`
- **THEN** the skill record SHALL expose the explicit no-evidence acknowledgement
- **AND** SHALL NOT be recorded as `verified`

#### Scenario: No evidence without acknowledgement never verifies
- **GIVEN** a clarification answer with no evidence and no explicit acknowledgement
- **WHEN** the proposal would otherwise apply
- **THEN** the system SHALL NOT set the skill to `verified`
- **AND** the skill SHALL remain `claimed`, `missing`, or `learning` per its existing state

#### Scenario: Removal of clarification-added evidence preserves provenance
- **GIVEN** a verified skill whose only evidence came from an accepted clarification
- **WHEN** the candidate removes that evidence
- **THEN** the skill SHALL expose a `verification_at_risk` flag while the state remains verified
- **AND** the removal SHALL NOT erase the origin answer reference from the historical record
