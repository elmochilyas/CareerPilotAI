## Why

Adding job opportunities manually is a data-entry burden that blocks candidates from reaching matching, resume tailoring, and application tracking. The candidate currently has no way to bring a job posting into CareerPilot.

Job opportunity ingestion is the sixth change in the approved delivery order. It unlocks the matching, resume, and application workflows that depend on trusted job data. Without it, the product cannot progress past profile and CV management.

The candidate must paste a job description, review AI-extracted structure, and confirm before the opportunity becomes trusted. No data is committed without explicit approval. No scraping. No browser automation. No matching in this change.

## What Changes

**New capability — Job opportunity ingestion**, added under the existing Opportunities domain.

### Storage and pipeline

- Accept a pasted job description with optional source URL and personal label.
- Validate description length, URL format, and ownership before storing.
- Process the description through an asynchronous queue pipeline: normalize → analyze with AI → validate structured output → resolve skills → persist suggestions.
- Track pipeline state with an ingestion state machine: `draft`, `queued`, `processing`, `review_ready`, `confirmed`, `failed`, `cancelled`.
- Use the existing Laravel AI SDK structured output pattern from CV ingestion.

### Three-domain separation

The proposal separates three logical concepts enforced by the data model:

1. **Job opportunity ingestion** — Temporary workflow record owned by the candidate. Contains the original description, source URL, label, status, failure info, retry data, and concurrency token. An ingestion is mutable during review and immutable after confirmation or cancellation.

2. **Extraction suggestions** — Untrusted AI-generated information with per-item review decisions and candidate edits. Each suggestion belongs to one ingestion and tracks its type, extracted value, edited value, review decision, source evidence, skill-resolution state, and schema version.

3. **Confirmed job opportunity** — Trusted candidate-approved data created only at confirmation time. Owned by the candidate profile. Contains the structured job information, requirements, and skill associations. The AI response never directly populates the opportunity table.

### Review and confirmation

- Candidate reviews structured suggestions through a dynamic nine-step interface showing only non-empty, active steps.
- Per-suggestion decisions: accept, edit-and-accept, reject, keep-blank, resolve-conflict.
- Grouped skills use a batch-save action. Responsibilities use list editing with keep, edit, remove, undo, and add support.
- Ambiguous skills require candidate resolution during review.
- Final preview is generated server-side and locked with a concurrency token.
- Confirmation runs inside a database transaction creating the opportunity, requirements, and skill associations atomically.

### Skill resolution

Reuses the existing canonical skills and aliases system. Skill requirements are job-level data, not profile changes. Resolved canonical skills link to the `skills` table. Unknown or ambiguous skills remain as original labels pending candidate resolution.

### Observability

- Processing runs record idempotency keys, pipeline version, and timestamps.
- AI extraction records provider, model, prompt version, latency, tokens, and status.
- Full job description and full AI response are never written to application logs.

### Contradictions resolved

The config.yaml planned table `opportunities` stores the final confirmed data but has no support for: the ingestion workflow state machine, per-field suggestion review with independent decisions, candidate edits stored separately from extracted values, skill-resolution tracking, or idempotent processing runs. This change introduces four new tables — `job_opportunity_ingestions`, `job_opportunity_suggestions`, `job_opportunities`, `job_opportunity_skills` — plus reuse of the existing `opportunities` table for confirmed data. The `opportunities` MLD table is renamed to `job_opportunities` for consistency.

## Capabilities

### New Capabilities

- `job-ingestion-create`: Accept pasted job description with optional URL and label. Validate, store, detect duplicates, queue processing. Candidate-scoped ownership.
- `job-ai-extraction`: Structured extraction of job information from pasted description using Laravel AI SDK. Strict server-side schema validation, prompt injection protection, provenance recording.
- `job-skill-resolution`: Map extracted skill requirements to canonical skills through exact match, alias resolution, ambiguous disambiguation, or unresolved label.
- `job-suggestion-review`: Dynamic nine-step review with per-item decisions, candidate edits, batch skill saves, list editing for responsibilities, and ambiguous-skill resolution.
- `job-preview-generate`: Server-side final preview with concurrency token. Must not create a confirmed opportunity or trigger matching.
- `job-confirmation-transactional`: Atomic creation of confirmed job opportunity with requirements and skill associations inside a database transaction. Idempotent. Rollback on failure.
- `job-ingestion-ui`: Full frontend experience with opportunities list, import form, processing screen, multi-step review, confirmation success, and readonly confirmed details.

### Modified Capabilities

- `opportunity-crud`: The Opportunities domain becomes active. New controllers, routes, and resources under `/api/v1/opportunities`.

## Impact

### Backend

- **Domain**: `app/Domain/Opportunities/` with Actions, Data, Enums, Policies, Services.
- **New controllers**: `JobOpportunityIngestionController`, `JobOpportunitySuggestionController`, `JobOpportunityConfirmedController` under `Api/V1/`.
- **New Form Requests**: 15+ request validators.
- **New API Resources**: `IngestionResource`, `SuggestionResource`, `OpportunityResource`, `PreviewResource`.
- **New queues**: `job-ingestion` queue with 3+ jobs: extraction, skill resolution, state management.
- **New config**: `config/job-ingestion.php` for limits, retry policy, schema version.
- **AI Operations domain**: New structured extraction schema for job analysis, following the `CvAnalyzer` abstraction pattern.
- **No new dependencies**: Use existing `laravel/framework` queue and filesystem. No new Composer packages.

### Frontend

- **New feature**: `src/features/opportunities/` with pages, components, composables, types, and API module.
- **New routes**: `/opportunities` list, `/opportunities/import`, `/opportunities/processing/:id`, `/opportunities/review/:id`, `/opportunities/:id` confirmed view, under DefaultLayout.
- **New components**: Import form, processing status, nine-step review workspace, step navigator, skill chip editor, responsibility list editor, preview summary, confirmation result, opportunity details card.
- **New composable**: `useJobIngestion` with queries for ingestion list, status, suggestions, preview, and mutations for create, save decisions, batch save, confirm.
- **No new frontend dependencies**.

### Database

- **New migrations**: `job_opportunity_ingestions`, `job_opportunity_suggestions`, `job_opportunity_skills` tables.
- **Existing tables**: `opportunities` (renamed to `job_opportunities` in the MLD), `companies`.
- **New indexes**: Ownership, status, duplicate-detection, review-state, processing-run lookups.
- **No existing columns changed**.

### Security and privacy

- **Ownership**: All ingestion and opportunity resources owned by the candidate. 404 for cross-user access.
- **Prompt injection**: Delimit untrusted job description in AI prompts. Validate all AI output against strict server-side schema.
- **Safe logging**: No full job description or full AI response in standard logs. Redacted for debugging.
- **Rate limiting**: Per-user limits on creation, retry, and confirmation.
- **Deletion**: Unconfirmed ingestions are fully deletable. Confirmed opportunities retain data but may be soft-deleted.
- **Queue safety**: Idempotency keys prevent duplicate suggestions. Jobs check existence before processing.

### Risks and assumptions

- **AI extraction quality**: LLM may miss or hallucinate information. Strict schema validation and mandatory candidate review mitigate this.
- **No URL fetching**: The optional source URL is metadata only. The candidate must paste the description directly.
- **Skill resolution**: Ambiguous skill names require candidate input. The system does not guess.
- **Queue worker**: Requires active `php artisan queue:work`. Assumed part of local setup.
- **Provider availability**: AI extraction failures are retryable. The candidate can retry or cancel.

### Out of scope

- URL fetching, scraping, or browser automation
- Job-board integrations or bulk import
- Email import
- Company research
- Candidate-to-job matching or match score
- Gap analysis
- Clarification questions
- CV tailoring, cover-letter generation, or interview preparation
- Job recommendations or automatic application
- Application tracking
- Notifications
- Monitoring source URL changes
- OCR or image-based extraction

### Acceptance criteria

- AC-JOB-01: Candidate pastes a valid job description. The system validates, stores, queues extraction, and shows processing status. The ingestion appears in the list with status `draft` then `queued` then `processing`.
- AC-JOB-02: AI extraction completes. The candidate sees structured suggestions with provenance. No job opportunity has been created yet.
- AC-JOB-03: Candidate reviews nine steps, accepts, edits, and rejects suggestions. Ambiguous skills prompt resolution. Responsibilities are editable as a list.
- AC-JOB-04: Candidate generates a server-side preview showing all fields to create, excluded items, warnings, and unresolved conflicts.
- AC-JOB-05: Preview confirms. Candidate confirms. The system creates the job opportunity with requirements and skill associations atomically. The ingestion becomes `confirmed`.
- AC-JOB-06: Candidate views the confirmed opportunity in readonly mode showing all structured fields.
- AC-JOB-07: Candidate uploads an identical description. The system returns a 409 conflict referencing the existing ingestion or opportunity.
- AC-JOB-08: Candidate attempts to confirm with incomplete review decisions. The system rejects with a clear blocking reason.
- AC-JOB-09: Candidate attempts to confirm with unresolved ambiguous skills. The system rejects.
- AC-JOB-10: Another candidate attempts to access the first candidate's ingestion or confirmed opportunity. The system returns 404.
- AC-JOB-11: Processing fails during AI analysis. Candidate sees `failed` status with a clear error code and retry option.
- AC-JOB-12: Candidate cancels or deletes an unconfirmed ingestion. The suggestions are removed. No confirmed data is lost.
- AC-JOB-13: Stale preview is rejected. Candidate must regenerate before confirming.
- AC-JOB-14: Idempotent confirmation does not create duplicate opportunities.
- AC-JOB-15: Candidate reanalyzes a cancelled ingestion in place. The system clears stale suggestions, queues a new versioned analysis attempt, and does not create a duplicate ingestion.

### Requirement IDs

- JOB-001: Accept pasted description within configured length limits.
- JOB-002: Optional source URL validated as HTTP or HTTPS.
- JOB-003: Queue AI extraction with idempotency and retry.
- JOB-004: Extract title, company, location, contract, seniority, responsibilities, skills, languages, education, compensation, and dates when present.
- JOB-005: Every requirement stores importance classification, source evidence.
- JOB-006: Version original content and normalized extractions.
- JOB-007: Candidate reviews, edits, and confirms before the opportunity is trusted.
- JOB-008: Safe failures expose controlled codes and allow retry or manual entry.
- JOB-009: Duplicate detection prevents accidental double creation.
- JOB-010: Skill resolution through canonical catalog with candidate input for ambiguous cases.
- JOB-011: Cancelled ingestions can be explicitly reanalyzed in place without duplicate records or stale-job writes.
