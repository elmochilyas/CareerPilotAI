## Purpose

Define the deterministic profile-to-job match engine: how the system computes an explainable, reproducible overall match and per-requirement results from trusted candidate profile data and confirmed opportunity requirements, how it versions and fingerprints snapshots, detects staleness, and fails safely — while the final numeric score always comes from deterministic Laravel code, never from the LLM.

## Requirements

### Requirement: Deterministic overall score
The system SHALL compute the overall match score with deterministic Laravel code using the approved weights (required skills 50%, preferred skills 20%, evidence relevance 15%, experience/education 10%, languages/soft skills 5%) and the documented rounding formula. The LLM SHALL NEVER produce the final numeric score.

#### Scenario: Reference candidate produces expected categories
- **GIVEN** a trusted profile (PHP, Laravel, MySQL, REST APIs, Sanctum, Git, queues) and a confirmed opportunity requesting PHP, Laravel, MySQL, REST APIs, queues, Docker, Pest, Redis
- **WHEN** the match analysis completes
- **THEN** the system SHALL return a completed analysis with an `overall_score` integer between 0 and 100 computed by the configured deterministic formula

#### Scenario: Same inputs produce the same score
- **GIVEN** identical profile and opportunity versions
- **WHEN** the match is computed twice
- **THEN** the two computations SHALL produce identical `overall_score`, category scores, and requirement states

#### Scenario: LLM cannot set the score
- **GIVEN** a semantic classifier response that contains a numeric score
- **WHEN** the engine combines results
- **THEN** the engine SHALL ignore any numeric score in the classifier output
- **AND** the stored `overall_score` SHALL be recomputed by deterministic Laravel code

### Requirement: Category scores
The system SHALL compute and store a separate score for each match category: required skills, preferred skills, evidence relevance, experience/education, and languages/soft skills.

#### Scenario: Category scores exposed
- **GIVEN** a completed match analysis
- **THEN** the analysis SHALL expose a score for each of the five categories
- **AND** the sum of the weighted category scores SHALL equal the `overall_score`

#### Scenario: Missing category data
- **GIVEN** a candidate profile with no education items
- **WHEN** the analysis completes
- **THEN** the experience/education category SHALL produce a defined neutral score
- **AND** the analysis SHALL record that the category had no candidate data rather than a false zero

### Requirement: Per-requirement match states
The system SHALL assign each confirmed requirement an independent match state: `matched`, `partial`, `gap`, or `unknown`. `unknown` SHALL mean the profile has no trustworthy evidence to decide and SHALL be distinct from `gap`.

#### Scenario: Verified skill requirement matched
- **GIVEN** a required skill the candidate holds in `verified` state
- **WHEN** the requirement is evaluated
- **THEN** the requirement SHALL have state `matched` with factor 1.00

#### Scenario: Missing skill requirement is a gap
- **GIVEN** a required skill with no candidate skill and no learning entry
- **WHEN** the requirement is evaluated
- **THEN** the requirement SHALL have state `gap` with factor 0.00

#### Scenario: Learning skill is partial
- **GIVEN** a required skill the candidate lists as `learning`
- **WHEN** the requirement is evaluated
- **THEN** the requirement SHALL have state `partial` with factor 0.20
- **AND** the analysis SHALL mark it as learning, never as professional experience

#### Scenario: Claimed skill is partial and shown separately
- **GIVEN** a required skill in `claimed` state without evidence
- **WHEN** the requirement is evaluated
- **THEN** the requirement SHALL have state `partial` with factor 0.50
- **AND** the claimed nature SHALL be exposed separately from verified results

#### Scenario: No trustworthy evidence yields unknown
- **GIVEN** an unstructured or semantic requirement (e.g. responsibility relevance) where the deterministic pass cannot decide and no trustworthy semantic comparison is available
- **WHEN** the requirement is evaluated
- **THEN** the requirement SHALL have state `unknown`
- **AND** the state SHALL NOT be reported as a gap

#### Scenario: Rejected or archived skill is a gap
- **GIVEN** a required skill in `rejected` or `archived` candidate state
- **WHEN** the requirement is evaluated
- **THEN** the requirement SHALL have state `gap` with factor 0.00

### Requirement: Importance is never flattened
The system SHALL preserve required-versus-preferred importance for every requirement in stored results and in every exposed representation.

#### Scenario: Required outweighs preferred
- **GIVEN** a job with required skills and preferred skills
- **WHEN** the category scores are computed
- **THEN** the required-skills weight (50%) SHALL be greater than the preferred-skills weight (20%)
- **AND** each requirement SHALL retain its `required` or `preferred` classification

#### Scenario: Requirement classification retained
- **GIVEN** a completed analysis
- **WHEN** the per-requirement results are returned
- **THEN** every result SHALL include the original importance classification of the source requirement

### Requirement: Non-duplicative evidence references
The system SHALL attach evidence references to every matched or partial result so each claim links to the exact candidate skill, evidence record, or profile item that supports it, without repeating the same reference across unrelated requirements.

#### Scenario: Evidence reference attached to result
- **GIVEN** a verified skill with linked evidence
- **WHEN** the requirement result is persisted
- **THEN** the result SHALL store a reference to the candidate skill and its evidence record
- **AND** the reference SHALL be displayed in the requirement workspace

#### Scenario: No evidence means no fabricated reference
- **GIVEN** a requirement with no supporting candidate data
- **WHEN** the requirement result is persisted
- **THEN** the result SHALL contain no evidence references
- **AND** the result SHALL show the requirement as `gap` or `unknown`, never as matched

#### Scenario: Source snippet provenance
- **GIVEN** a requirement sourced from a confirmed opportunity requirement
- **WHEN** the result is persisted
- **THEN** the result SHALL reference the source requirement record
- **AND** SHALL retain the original requirement text used at analysis time

### Requirement: Critical missing requirements produce independent warnings
The system SHALL surface critical missing requirements as warnings independently of the numeric score.

#### Scenario: Critical gap warning despite moderate score
- **GIVEN** an analysis where Docker and Redis are required and missing
- **WHEN** the brief is generated
- **THEN** Docker and Redis SHALL appear in a critical-missing warnings list
- **AND** the warnings SHALL be displayed separately from the overall score

### Requirement: Fingerprints and versioned snapshots
Every match analysis SHALL store fingerprints and exact source versions so it can be reproduced and explained later.

#### Scenario: Snapshots store versions
- **GIVEN** a completed analysis
- **THEN** the analysis SHALL store the profile fingerprint, opportunity fingerprint, algorithm version, scoring version, profile version, and job-analysis version

#### Scenario: Changed source changes the fingerprint
- **GIVEN** a completed analysis for a profile
- **WHEN** the profile gains a verified skill and the match is recomputed
- **THEN** the new analysis SHALL store a different profile fingerprint from the previous analysis

### Requirement: Staleness detection and recalculation
The system SHALL detect when the profile or opportunity changed since an analysis and SHALL keep the old result visible with a stale notice and a recalculate action.

#### Scenario: Profile changed after analysis
- **GIVEN** a completed analysis
- **WHEN** the candidate edits their profile
- **THEN** the analysis SHALL be marked stale
- **AND** the old score SHALL remain readable with the stale notice

#### Scenario: Opportunity changed after analysis
- **GIVEN** a completed analysis
- **WHEN** the confirmed opportunity requirements change
- **THEN** the analysis SHALL be marked stale
- **AND** the old result SHALL remain readable

#### Scenario: Recalculate creates a new snapshot
- **GIVEN** a stale analysis
- **WHEN** the candidate requests recalculation
- **THEN** the system SHALL create a new analysis snapshot with new fingerprints
- **AND** the previous analysis SHALL remain stored unchanged

### Requirement: Analysis lifecycle is asynchronous and safe
Match analysis creation SHALL follow an asynchronous lifecycle with idempotency and safe failure; a failed analysis SHALL NOT leave a partial trusted result.

#### Scenario: Analysis queued and completed
- **GIVEN** a confirmed opportunity owned by the candidate
- **WHEN** the candidate requests a match
- **THEN** the analysis SHALL transition through `queued` and `processing`
- **AND** SHALL reach `completed` with scores and results persisted atomically

#### Scenario: Duplicate request is idempotent
- **GIVEN** an analysis already queued or processing for the same profile and opportunity
- **WHEN** a duplicate request arrives with the same stable operation key
- **THEN** the system SHALL NOT create a second analysis
- **AND** SHALL return the existing operation

#### Scenario: Provider failure leaves no partial result
- **GIVEN** a semantic comparison fails during analysis
- **WHEN** the analysis completes or fails
- **THEN** the system SHALL persist either a fully completed analysis or a failed analysis with a stable failure code
- **AND** SHALL NOT persist a score derived from partial classifier output

### Requirement: Insufficient-profile gate
The system SHALL refuse to produce a misleading score when the candidate profile lacks the minimum trusted data required for a meaningful match.

#### Scenario: Incomplete profile produces a gate
- **GIVEN** a candidate with no active profile or no trusted skills
- **WHEN** they request a match
- **THEN** the system SHALL return a gate state indicating the profile is insufficient for matching
- **AND** SHALL NOT return a numeric score as if it were meaningful

#### Scenario: Sufficient profile proceeds
- **GIVEN** a candidate with a trusted profile containing skills and evidence
- **WHEN** they request a match
- **THEN** the system SHALL proceed with analysis

### Requirement: Match findings expose tailoring relevance
Match findings SHALL include a `tailoring_relevance` field with values `high`, `medium`, `low`, or `none`, computed from the match state and importance. Findings with `match_state = matched` and `importance = required` SHALL have `tailoring_relevance = high`. Findings with `match_state = partial` SHALL have `tailoring_relevance = medium`. Gaps SHALL have `tailoring_relevance = none`. This field is read-only and computed deterministically from existing finding attributes.

#### Scenario: Matched required finding has high tailoring relevance
- **WHEN** a match finding has `match_state = matched` and `importance = required`
- **THEN** the finding includes `tailoring_relevance = high`

#### Scenario: Partial finding has medium tailoring relevance
- **WHEN** a match finding has `match_state = partial`
- **THEN** the finding includes `tailoring_relevance = medium`

#### Scenario: Gap finding has no tailoring relevance
- **WHEN** a match finding has `match_state = gap`
- **THEN** the finding includes `tailoring_relevance = none`

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
