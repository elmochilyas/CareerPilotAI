## ADDED Requirements

### Requirement: Tailored CV creation requires confirmed opportunity and completed match analysis
The system SHALL create a tailored CV only when the candidate owns a confirmed `JobOpportunity` with a completed `MatchAnalysis` (status `completed`). The system SHALL reject creation with `tailoring_prerequisites_not_met` when either is missing.

#### Scenario: Successful tailoring initiation
- **WHEN** the candidate requests tailoring for an opportunity with a completed match analysis
- **THEN** the system creates a `Resume` record with status `draft` and returns the tailoring workspace resource

#### Scenario: Missing match analysis
- **WHEN** the candidate requests tailoring for a confirmed opportunity without a completed match analysis
- **THEN** the system returns 422 with problem code `tailoring_prerequisites_not_met`

#### Scenario: Unconfirmed opportunity
- **WHEN** the candidate requests tailoring for an unconfirmed opportunity
- **THEN** the system returns 404 (ownership scoping hides unconfirmed opportunities)

### Requirement: Tailoring content is derived exclusively from trusted profile data
The system SHALL select, reorder, and prioritize CV content exclusively from the candidate's trusted `CandidateProfile`, `ProfileItem` records, `CandidateSkill` records (states `verified` or `claimed`), and associated `Evidence`. The system SHALL NOT invent skills, experience, education, projects, certifications, languages, metrics, dates, or achievements that do not exist in the trusted profile.

#### Scenario: All content traceable to trusted sources
- **WHEN** a tailored CV is generated
- **THEN** every section, bullet, and skill in the structured content references a `source_type` and `source_id` pointing to a trusted profile entity

#### Scenario: Missing profile sections produce empty CV sections
- **WHEN** the candidate has no profile items of a given type (e.g., no certifications)
- **THEN** the tailored CV omits that section rather than fabricating content

#### Scenario: Rejected and archived skills are excluded
- **WHEN** a `CandidateSkill` has state `rejected` or `archived`
- **THEN** it SHALL NOT appear in the tailored CV under any section

### Requirement: Section selection and ordering based on match relevance
The system SHALL determine which CV sections to include and their ordering based on the `MatchAnalysis` findings. Matched strengths (`match_state = matched`) SHALL be emphasized. Partial matches SHALL be included truthfully with appropriate framing. Gaps SHALL NOT be fabricated. The system SHALL prioritize sections by relevance score derived from match category weights.

#### Scenario: Strong match emphasizes relevant sections
- **WHEN** the match analysis shows strong `matched` findings in RequiredSkills and ExperienceEducation categories
- **THEN** the tailored CV places skills and experience sections prominently, ordered by relevance to the job requirements

#### Scenario: Partial matches included truthfully
- **WHEN** a skill has `match_state = partial` with `factor = 0.50`
- **THEN** the skill appears in the tailored CV with its actual claimed state, not as verified

#### Scenario: Gaps are omitted, not fabricated
- **WHEN** a required skill has `match_state = gap`
- **THEN** the skill SHALL NOT appear in the tailored CV as a possessed skill

### Requirement: Skills prioritization based on match importance
The system SHALL order candidate skills in the tailored CV by: (1) match importance (required before preferred), (2) match state (matched before partial before claimed), (3) evidence count descending. Skills with no match relevance may appear at the end as general skills.

#### Scenario: Required matched skills appear first
- **WHEN** the candidate has verified skills matching required job skills and claimed skills matching preferred skills
- **THEN** required matched skills appear before preferred matched skills in the skills section

#### Scenario: Skills without match relevance are demoted
- **WHEN** a candidate skill has no corresponding match finding
- **THEN** it appears after all match-relevant skills

### Requirement: Experience and project prioritization
The system SHALL order `ProfileItem` records of type `Experience` and `Project` by relevance to the job's match findings. Items whose description or associated skills overlap with matched requirements SHALL appear first. Items with no match relevance SHALL appear after relevant items or be omitted when the candidate has many items.

#### Scenario: Relevant experience appears first
- **WHEN** a candidate has three experience items and one strongly matches the job's required skills
- **THEN** the matching item appears first in the experience section

#### Scenario: Many items are truncated
- **WHEN** a candidate has more than 6 experience items and only 3 are relevant to the job
- **THEN** the tailored CV includes the 3 relevant items plus the 2 most recent non-relevant items, omitting the rest

### Requirement: AI wording improvements are reviewable proposals
The system MAY use AI to propose wording improvements for CV bullets, summaries, and descriptions. Every AI-proposed wording change SHALL be presented as a reviewable proposal with `original_text` and `proposed_text` fields. The candidate MUST review and accept each proposal before it becomes part of the finalized CV. AI SHALL NOT modify factual content — only wording, clarity, order, and emphasis.

#### Scenario: AI proposes wording improvement
- **WHEN** the tailoring AI processes a profile item bullet
- **THEN** the system stores the original text and proposed text as a `TailoringProposal` with status `proposed`

#### Scenario: Candidate accepts wording proposal
- **WHEN** the candidate accepts a tailoring proposal
- **THEN** the proposal status becomes `accepted` and the finalized content uses the proposed text

#### Scenario: Candidate reverts accepted proposal
- **WHEN** the candidate reverts an accepted proposal before finalization
- **THEN** the proposal status returns to `proposed` and the content reverts to `original_text`

#### Scenario: AI does not invent factual claims
- **WHEN** the AI proposes a wording change that adds a factual claim not present in the original
- **THEN** the schema validator rejects the proposal and the original text is preserved

### Requirement: Traceability of tailored claims
Every claim in the finalized tailored CV SHALL carry a `source_ref` indicating its origin: `profile_item:{id}`, `candidate_skill:{id}`, or `profile_field:{field_name}`. This traceability SHALL be exposed in the API response and available in the UI for inspection.

#### Scenario: API exposes source references
- **WHEN** the client requests a tailored CV resource
- **THEN** each content item includes a `source_ref` field pointing to the trusted source

### Requirement: Preview before finalization
The system SHALL provide a preview endpoint that returns the finalized CV content as it would appear when exported, before the candidate approves it. The preview SHALL render a clean, CV-focused layout without editing controls.

#### Scenario: Preview shows complete CV
- **WHEN** the candidate requests a preview of a draft tailored CV
- **THEN** the system returns the complete structured content with section ordering, prioritized skills, and accepted wording proposals applied

### Requirement: Save and immutabilize tailored CV
When the candidate approves a tailored CV, the system SHALL set `status = approved`, record `approved_at` timestamp, and make the record immutable. Subsequent edits SHALL create a new version (new `Resume` record linked to the same opportunity).

#### Scenario: Approval makes CV immutable
- **WHEN** the candidate approves a tailored CV
- **THEN** the record's `status` becomes `approved`, `approved_at` is set, and UPDATE requests return 409 `resume_immutable`

#### Scenario: Editing creates new version
- **WHEN** the candidate requests edits to an approved tailored CV
- **THEN** the system creates a new `Resume` record with `status = draft` linked to the same opportunity, copying the approved content

### Requirement: Staleness detection when source data changes
When the candidate modifies their `CandidateProfile` or `CandidateSkill` records after a tailored CV is created, the system SHALL mark the tailored CV stale via fingerprint comparison. Staleness SHALL be visible in the API response and trigger a re-tailor prompt in the UI.

#### Scenario: Profile change marks CV stale
- **WHEN** the candidate updates a profile item after creating a tailored CV
- **THEN** the next GET request for that resume shows `stale = true` with a `stale_reason` indicating which source changed

#### Scenario: Re-tailoring from stale CV
- **WHEN** the candidate initiates re-tailoring from a stale CV
- **THEN** the system creates a new draft version using the current profile and match analysis state

### Requirement: Version history
The system SHALL maintain a version history of tailored CVs per opportunity. Each version SHALL have a `version_no` (auto-incrementing per opportunity) and a status. The candidate SHALL be able to list all versions for an opportunity.

#### Scenario: List version history
- **WHEN** the candidate requests the version history for an opportunity
- **THEN** the system returns all `Resume` records for that opportunity ordered by `version_no` descending

### Requirement: Ownership and authorization
Every `Resume` record SHALL be scoped to the owning candidate via `candidate_profile_id`. The system SHALL enforce ownership via `ResumePolicy` on every lookup. Cross-user access attempts SHALL return 404.

#### Scenario: Cross-user access blocked
- **WHEN** user B attempts to access a resume owned by user A
- **THEN** the system returns 404

#### Scenario: Unauthenticated access blocked
- **WHEN** an unauthenticated request accesses a resume endpoint
- **THEN** the system returns 401

### Requirement: Error contracts
The system SHALL return RFC 9457-style problem details for all error cases with stable problem codes:
- `tailoring_prerequisites_not_met` — missing opportunity or match analysis
- `resume_not_found` — resume does not exist or is not owned
- `resume_immutable` — attempting to modify an approved resume
- `resume_stale` — attempting to finalize a stale resume
- `tailoring_ai_failed` — AI provider failure; fallback used
- `validation_error` — input validation failure

#### Scenario: Problem details on validation error
- **WHEN** the client submits a resume update with invalid content
- **THEN** the system returns 422 with RFC 9457 problem details including `code` and `errors` fields
