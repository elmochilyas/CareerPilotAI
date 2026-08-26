## MODIFIED Requirements

### Requirement: Version history
The system SHALL maintain a version history of tailored CVs per opportunity. Each version SHALL have a `version_no` (auto-incrementing per opportunity) and a status. The candidate SHALL be able to list all versions for an opportunity. Creating a new version SHALL compute the next `version_no` from the existing records for the same candidate profile and opportunity under concurrency protection, so parallel creations cannot produce duplicate version numbers. An approved previous version SHALL NOT prevent creating a new draft version, and the absence of previous versions SHALL yield `version_no = 1`. The uniqueness scope SHALL be per opportunity — never a global uniqueness rule across the user's resumes.

#### Scenario: List version history
- **WHEN** the candidate requests the version history for an opportunity
- **THEN** the system returns all `Resume` records for that opportunity ordered by `version_no` descending

#### Scenario: First version starts at one
- **WHEN** the candidate creates the first tailored CV for an opportunity
- **THEN** the created record has `version_no = 1`

#### Scenario: Version number increments across statuses
- **WHEN** an opportunity already has a `draft` or `approved` resume and no blocking draft exists
- **THEN** creating a new tailored CV assigns the next sequential `version_no` (existing maximum plus one)

#### Scenario: Approved version does not block a new draft
- **WHEN** the only existing resume for an opportunity is `approved`
- **THEN** the candidate can create a new draft version for the same opportunity

#### Scenario: Existing data survives migration
- **WHEN** the versioning behavior is applied to a database containing resumes created before it existed
- **THEN** those rows keep their identity and content and remain addressable through the API

### Requirement: Staleness detection when source data changes
When the candidate modifies their `CandidateProfile` or `CandidateSkill` records after a tailored CV draft is created, the system SHALL mark the tailored CV stale via fingerprint comparison against the draft's own stored profile and opportunity snapshots. Opportunity modifications (`JobOpportunity` content) SHALL equally mark dependent drafts stale. Staleness SHALL be visible in the API response and trigger a re-tailor prompt in the UI.

#### Scenario: Profile change marks CV stale
- **WHEN** the candidate updates a profile item after creating a tailored CV draft
- **THEN** the next GET request for that resume shows `stale = true` with a `stale_reason` indicating which source changed

#### Scenario: Opportunity change marks CV stale
- **WHEN** the confirmed opportunity content changes after a tailored CV draft was generated from it
- **THEN** the draft shows `stale = true` with a reason indicating the opportunity changed

#### Scenario: Re-tailoring from stale CV
- **WHEN** the candidate initiates re-tailoring from a stale CV (or regenerates its content)
- **THEN** the system refreshes the draft's snapshots/fingerprints using the current profile and opportunity state and the draft reports `stale = false`

### Requirement: Save and immutabilize tailored CV
When the candidate approves a tailored CV, the system SHALL set `status = approved`, record `approved_at` timestamp, and make the record immutable. Approval SHALL be rejected with HTTP 409 and problem code `resume_stale` while the draft is stale relative to its own snapshots; the rejection message SHALL instruct the candidate to regenerate the CV before approving. Subsequent edits SHALL create a new version (new `Resume` record linked to the same opportunity).

#### Scenario: Approval makes CV immutable
- **WHEN** the candidate approves a tailored CV
- **THEN** the record's `status` becomes `approved`, `approved_at` is set, and UPDATE requests return 409 `resume_immutable`

#### Scenario: Editing creates new version
- **WHEN** the candidate requests edits to an approved tailored CV
- **THEN** the system creates a new `Resume` record with `status = draft` linked to the same opportunity, copying the approved content

#### Scenario: Stale draft cannot be approved
- **WHEN** the candidate approves a draft whose source profile or opportunity changed after generation
- **THEN** the system returns HTTP 409 with code `resume_stale` and does not change the draft status

#### Scenario: Regenerated draft can be approved
- **WHEN** the candidate regenerates/re-tailors a stale draft so its snapshots match current source data
- **THEN** approval succeeds and immutabilizes the record

### Requirement: Section selection and ordering based on match relevance
The system SHALL determine which CV sections to include and their ordering based on the `MatchAnalysis` findings. Matched strengths (`match_state = matched`) SHALL be emphasized. Partial matches SHALL be included truthfully with appropriate framing. Gaps SHALL NOT be fabricated. The system SHALL prioritize sections by relevance score derived from match category weights. When explicit per-item tailoring relevance is absent from findings, the system SHALL derive item relevance deterministically from trusted data only (opportunity required/preferred skills, job requirements, profile item titles/descriptions/technologies, candidate skills, and existing normalization/similarity utilities), without inventing facts or source identifiers. When relevance data is sparse, selection SHALL preserve a minimum content floor so the tailored CV retains sensible core sections instead of becoming empty.

#### Scenario: Strong match emphasizes relevant sections
- **WHEN** the match analysis shows strong `matched` findings in RequiredSkills and ExperienceEducation categories
- **THEN** the tailored CV places skills and experience sections prominently, ordered by relevance to the job requirements

#### Scenario: Partial matches included truthfully
- **WHEN** a skill has `match_state = partial` with `factor = 0.50`
- **THEN** the skill appears in the tailored CV with its actual claimed state, not as verified

#### Scenario: Gaps are omitted, not fabricated
- **WHEN** a required skill has `match_state = gap`
- **THEN** the skill SHALL NOT appear in the tailored CV as a possessed skill

#### Scenario: Deterministic fallback without explicit relevance
- **WHEN** no finding carries an explicit tailoring-relevance value for an item
- **THEN** the system computes that item's relevance deterministically from trusted profile and opportunity data and produces identical scores for identical inputs

#### Scenario: Sparse relevance keeps a minimum content floor
- **WHEN** relevance signals are sparse or absent for most profile content
- **THEN** the tailored CV still includes core truthful sections (for example summary and highest-value trusted items) rather than empty content
