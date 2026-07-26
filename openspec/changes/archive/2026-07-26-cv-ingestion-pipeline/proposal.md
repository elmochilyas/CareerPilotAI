## Why

Building a trusted career profile requires entering substantial structured data manually. CV ingestion reduces friction by letting candidates upload their existing CV and review suggested information before committing it to their profile. This is the fifth change in the approved delivery order and unlocks the matching, resume, and application workflows that depend on a populated profile.

Manual profile entry is the top barrier for new users. A CV already contains most of the information a junior candidate needs in their profile. Without ingestion, the user types everything from scratch — increasing abandonment before the first match or resume.

## What Changes

**New domain — CV Ingestion**, added to the existing modular monolith under `app/Domain/CvIngestion/`.

### Storage and pipeline

- Accept PDF and DOCX CV uploads with server-side file validation (extension, MIME signature, size, corrupt detection, password protection, empty document, disguised content).
- Store files under private, non-public paths with generated opaque names and candidate-scoped ownership.
- Process uploads through an asynchronous queue pipeline: validate → store → extract text → analyze with AI → validate suggestions → persist suggestions.
- Track pipeline state with a document state machine: `pending`, `processing`, `extracting`, `analyzing`, `ready_for_review`, `failed`, `imported`, `deleted`.

### Suggestions

- Every extracted data point is a mutable, individually-reviewable suggestion with provenance (source page, text snippet, extraction method, schema version).
- A suggestion covers: headline, summary, social links, experience, education, projects, certifications, languages, and skills.
- No extracted data is trusted automatically. Nothing modifies the profile without explicit candidate action.

### Review and import

- Candidate reviews suggestions with a side-by-side comparison: current profile value vs CV extracted value.
- Per-suggestion decisions: accept, edit-and-accept, reject, keep-existing, create-new, update-existing.
- After reviewing all suggestions, the candidate sees a final import summary with conflicts and duplicates.
- Apply approved changes in a single database transaction. Partial failure rolls back the entire import.
- Skills accepted from a CV become `claimed` — never `verified` — in the candidate skills state machine.

### Observability

- Processing runs record pipeline steps, timing, errors, and idempotency keys.
- AI extraction records provider, model, prompt version, latency, tokens, and status.
- Raw CV text and full extracted payloads are never written to application logs.

### Contradictions resolved

The MLD `files` table's `extracted_data` JSON column cannot support individual suggestion review, independent accept/reject decisions, provenance tracking, or transactional import grouping. This change introduces four new tables — `cv_documents`, `cv_processing_runs`, `cv_suggestions`, `cv_import_batches` — superseding `files.extracted_data` for the CV ingestion purpose. The `files` table continues to serve its generic purpose; `cv_documents` owns the CV-specific lifecycle.

## Capabilities

### New Capabilities

- `cv-document-management`: Upload, validate, store privately, list, show status, preview, retry processing, and delete CV documents with candidate-scoped ownership and a state machine.
- `cv-text-extraction`: Deterministic PDF and DOCX text extraction using Laravel parsers before AI analysis, with a provider abstraction for parser selection.
- `cv-ai-analysis`: Structured extraction of professional information from CV text using an AI provider, with strict server-side schema validation, prompt injection protection, and provenance recording.
- `cv-suggestion-review`: Per-suggestion accept, edit-and-accept, reject, keep-existing, create-new, and update-existing decisions with side-by-side comparison of current profile versus CV data.
- `cv-import-apply`: Transactional application of approved suggestions to the candidate profile, with ownership, duplicate, concurrency, and business-rule revalidation before commit.
- `cv-ingestion-ui`: Four-stage guided upload-to-import Vue interface with accessible drag-and-drop, processing progress, structured review workspace, import preview, and success/recovery states.

### Modified Capabilities

- `profile-completion`: Profile completion percentage must not include skills accepted from CV ingestion. Skills remain outside the completion calculation. Completion changes only after successfully importing confirmed profile information.
- `candidate-skills`: Skills imported from CV analysis must enter the `claimed` state. The skill state machine must reject automatic `verified` transitions from ingestion.

## Impact

### Backend

- **New domain**: `app/Domain/CvIngestion/` with Actions, Data, Enums, Events, Policies, Services.
- **New controllers**: `CvDocumentController`, `CvSuggestionController`, `CvImportController` under `Api/V1/`.
- **New Form Requests**: 10+ request validators for upload, review decisions, and import.
- **New API Resources**: `CvDocumentResource`, `CvSuggestionResource`, `CvProcessingRunResource`, `CvImportBatchResource`.
- **New queues**: `cv-ingestion` queue with 4+ jobs: text extraction, AI analysis, suggestion validation, import apply.
- **New config**: `config/cv-ingestion.php` for size limits, MIME types, retry policy, storage paths.
- **AI Operations domain**: The existing empty `AIOperations` domain skeleton gets its first consumer. An AI adapter interface and OpenAI implementation are created here or in the CV ingestion domain with a provider abstraction.
- **No new dependencies**: Use existing `laravel/framework` filesystem, queue, storage capabilities. No new Composer packages for PDF/DOCX parsing — evaluate Laravel-compatible solutions within existing dependencies first.

### Frontend

- **New feature**: `src/features/cv-ingestion/` with pages, components, composables, types, and API module.
- **New route**: `/profile/import` or a dedicated CV page — inspect current navigation for cleanest option.
- **New components**: Upload zone, file summary, processing status, review workspace, suggestion card, comparison view, import preview, import result.
- **New composable**: `useCvIngestion` with queries for document list, suggestions, import preview and mutations for upload, save decisions, apply import.
- **No new frontend dependencies**.
- **Accessibility**: WCAG 2.2 AA, keyboard drag-and-drop, screen-reader announcements, focus management, reduced motion.

### Database

- **New migrations**: `cv_documents`, `cv_processing_runs`, `cv_suggestions`, `cv_import_batches` tables.
- **New indexes**: Ownership, status, processing-run lookups, suggestion review-state.
- **No new columns on existing tables** — accepted data flows into existing `candidate_profiles`, `profile_items`, `candidate_skills`.

### Security and privacy

- **Private storage**: Candidate-owned files under `storage/app/private/cv-ingestion/` with generated opaque names. No public URLs. Authorization-checked download.
- **File validation**: Extension + MIME signature + size + corrupt + password + empty + disguised archive checks.
- **Prompt injection**: Delimit untrusted CV content in AI prompts. Validate all AI output against strict server-side schema.
- **No raw CV text in logs**: Redacted for debugging, never logged at info level.
- **Deletion**: Hard-delete private file and suggestions on document deletion. Trusted imported profile data is not deleted.
- **Queue replay**: Idempotency keys prevent duplicate suggestions. Processing after document deletion is safe (job checks existence first).

### Risks and assumptions

- **OCR not included**: Scanned PDFs (image-only) are out of scope for MVP. The system detects image-only PDFs and returns a clear error. OCR requires separate infrastructure and will be evaluated post-MVP.
- **Malware scanning not included**: No antivirus or sandboxing in MVP. File validation (MIME signature, structural checks) reduces risk. Malware scanning is deferred to a future security change.
- **AI provider key**: The project has no AI provider configured yet. This change assumes the AI provider configuration is set up as part of the AI Operations domain or environment setup before pipeline execution.
- **PDF/DOCX parser**: Laravel 13 does not ship with PDF or DOCX parsers. This change assumes a lightweight PHP parser (e.g., `smalot/pdfparser` or `PhpOffice/PhpWord`) is added. No commercial or cloud parsing service at MVP.
- **Queue worker must run**: The database queue driver requires an active `php artisan queue:work` process. The proposal assumes this is part of the local development setup.

### Out of scope

- OCR for scanned/image PDFs
- Malware or virus scanning
- Job ingestion, matching, or resume generation
- Automatic skill verification
- Public CV sharing or recruiter access
- Microservices or external parsing APIs
- Redis, Horizon, or additional infrastructure
- Bulk CV import or batch processing

### Acceptance criteria

- AC-CVIN-01: A candidate uploads a valid PDF CV. The system validates, stores privately, queues extraction, and shows processing status. The document appears in the document list with status `pending` then `processing`.
- AC-CVIN-02: Pipeline completes. The candidate sees structured suggestions with provenance. No profile data has been modified yet.
- AC-CVIN-03: Candidate accepts headline, edits summary text, rejects a duplicated experience, and accepts two skills. The import preview shows 1 create-profile-item, 1 skip, 2 claimed-skills.
- AC-CVIN-04: Candidate confirms import. Profile is updated with accepted headline and summary. A new experience item is created. Two skills appear as `claimed`. Profile completion increases accordingly.
- AC-CVIN-05: Candidate uploads a DOCX disguised as a PDF. System rejects with `file_type_mismatch` error. No file is stored.
- AC-CVIN-06: Candidate uploads a password-protected PDF. System rejects with `file_protected` error. No file is stored.
- AC-CVIN-07: Candidate uploads a large file exceeding the limit. System rejects with `file_too_large` error.
- AC-CVIN-08: A candidate uploads a CV. Another candidate attempts to access it by guessing the document ID. System returns 404.
- AC-CVIN-09: Candidate uploads, reviews, and imports. Later deletes the CV document. File is removed, suggestions are removed, processing run history remains (for audit). Profile data stays intact.
- AC-CVIN-10: Processing fails during AI analysis. Candidate sees `failed` status with a clear error code and retry option.
- AC-CVIN-11: Candidate attempts to apply an import that conflicts with profile changes made during review. System rejects with concurrency error and prompts re-review.
- AC-CVIN-12: Duplicate upload of the same file returns a 409 conflict referencing the existing document.
- AC-CVIN-13: Skills imported from CV appear as `claimed`, never `verified`.
- AC-CVIN-14: Frontend meets WCAG 2.2 AA for keyboard navigation, screen-reader announcements, and focus management during upload, review, and import.

### Requirement IDs

- CVIN-001: Accept PDF/DOCX within configured size and MIME-signature limits.
- CVIN-002: Store privately with generated names and scan status.
- CVIN-003: Queue extraction and AI structuring; make operations idempotent.
- CVIN-004: Show confidence and source-page references when available.
- CVIN-005: Candidate accepts, edits, or rejects items before profile merge.
- CVIN-006: Safe failures expose controlled codes and allow retry or manual entry.
