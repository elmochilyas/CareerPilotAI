## ADDED Requirements

### Requirement: Uncertain findings expose clarification candidates
The system SHALL expose high-impact uncertain findings as clarification candidates with a deterministic eligibility signal so the clarification workflow can generate questions instead of silently accepting a low or uncertain score.

#### Scenario: Partial claimed finding is a clarification candidate
- **GIVEN** a required-skill finding in `partial` state with factor 0.50 (claimed skill without evidence)
- **WHEN** the engine evaluates clarification eligibility
- **THEN** the finding SHALL be exposed as a clarification candidate
- **AND** SHALL carry the evidence basis and requirement reference needed to build a question

#### Scenario: Gap without candidate data is a clarification candidate
- **GIVEN** a required-skill finding in `gap` state with factor 0.00 and no candidate skill record
- **WHEN** the engine evaluates clarification eligibility
- **THEN** the finding SHALL be exposed as a clarification candidate

#### Scenario: Verified or matched finding is not a candidate
- **GIVEN** a finding in `matched` state backed by a verified skill
- **WHEN** the engine evaluates clarification eligibility
- **THEN** the finding SHALL NOT be a clarification candidate

#### Scenario: Rejected or archived skill is not a candidate
- **GIVEN** a finding whose candidate skill is `rejected` or `archived`
- **WHEN** the engine evaluates clarification eligibility
- **THEN** the finding SHALL NOT be a clarification candidate

### Requirement: Accepted clarification drives staleness, not silent scoring
The engine SHALL treat an accepted, profile-changing clarification answer as a source change: the owning analysis SHALL be marked stale through the existing fingerprint mechanism and the score SHALL NOT be recomputed during the mutation.

#### Scenario: Accepted answer marks analysis stale
- **GIVEN** an analysis whose trusted profile changed via an accepted clarification
- **WHEN** the fingerprint service next evaluates the analysis
- **THEN** the analysis SHALL be flagged stale
- **AND** the previously stored score SHALL remain readable

#### Scenario: No silent score change from clarification
- **GIVEN** an accepted clarification that changes a trusted skill
- **WHEN** the mutation is applied
- **THEN** the stored overall score SHALL NOT change as a side effect of the mutation
- **AND** a new score SHALL only be produced by a fresh recalculation snapshot

#### Scenario: Recalculation reflects clarified state
- **GIVEN** an analysis marked stale after an accepted clarification
- **WHEN** the candidate requests recalculation
- **THEN** the new snapshot SHALL be computed from the updated trusted profile
- **AND** the finding that was clarified SHALL be re-evaluated against the new trusted state
