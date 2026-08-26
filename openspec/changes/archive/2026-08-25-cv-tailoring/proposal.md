## Why

A candidate who has a trusted profile and a confirmed job opportunity currently has no way to produce a job-specific CV version from that trusted data. The match analysis identifies strengths, gaps, and partial matches, but the candidate cannot translate that intelligence into a tailored, reviewable resume artifact without leaving CareerPilot and manually rewriting their CV. `openspec/config.yaml` reserves the `resume-tailoring-versioning` change (item 11 in the approved order) and defines the core invariants: RES-001 (generate from trusted profile data, selected analysis, and template), RES-002 (improve truthful content without inventing facts), RES-003 (candidate edits before approval), and RES-004 (approved versions are immutable). The `resumes` table is defined in `docs/database/MLD.md` but no migration, model, domain, or API exists yet. This change implements the tailoring workflow that turns a confirmed opportunity plus its match analysis into a reviewable, versioned, job-specific CV artifact — the central deliverable of CareerPilot's product promise.

## What Changes

- **Tailored CV creation**: from a confirmed opportunity and its latest completed match analysis, the system analyzes which trusted profile content is most relevant and produces a structured CV draft with section selection, content reordering, skills prioritization, and job-specific wording improvements — all derived exclusively from trusted profile/evidence data.
- **AI boundaries for tailoring**: AI may reword, reorder, select, and prioritize from trusted data; AI must never invent skills, experience, education, projects, certifications, languages, metrics, dates, or achievements. Every tailored claim remains traceable to a trusted profile source. AI-proposed wording changes are presented as proposals the candidate reviews before acceptance.
- **Review/edit/accept/reject workflow**: the candidate sees a tailoring workspace with proposed changes clearly distinguished from original content. Sections, reordered items, reworded bullets, and prioritized skills each have Accept / Edit / Revert controls. Unchanged content is collapsed or hidden to avoid overwhelm. Side-by-side comparison is available where meaningful.
- **Preview and finalization**: after reviewing all proposed changes, the candidate previews the final CV and saves it as an immutable tailored version linked to the opportunity.
- **Versioning and staleness**: each tailored CV is a snapshot. When the source profile or opportunity changes, existing tailored CVs are marked stale (fingerprint-based). The candidate can view staleness status and choose to re-tailor.
- **Resume domain activation**: new `Resumes` domain with Actions, Data, Enums, Policies, and Services implementing the tailoring workflow, CRUD for tailored CVs, and version management.
- **API endpoints**: REST endpoints under `/api/v1` for creating, listing, showing, updating, approving, andpreviewing tailored CVs, with ownership scoping and RFC 9457 errors.
- **Frontend workspace**: a new tailoring workspace page accessible from the opportunity detail or match brief, with a step-based flow: create → review changes → preview → save. Mobile remains usable; desktop provides the richer side-by-side editing experience. Follows `docs/design/CAREERPILOT_PREMIUM_DESIGN_SYSTEM.md`.

## Capabilities

### New Capabilities

- `cv-tailoring`: Backend domain workflow — create a tailored CV from trusted profile + match analysis, AI-assisted section selection/reordering/rewording/skills prioritization, review/edit/accept/reject UI controls, preview, finalization, versioning, staleness detection, and the tailored CV API.
- `cv-tailoring-ui`: Frontend tailoring workspace — step-based flow, proposed-change cards with accept/edit/revert, side-by-side comparison, preview panel, save/approve, all state coverage, responsive and accessible per the design system.
- `resume-api`: Resume CRUD API — create, list, show, update (content edits), approve (immutabilize), andpreview endpoints with ownership policies, RFC 9457 errors, and OpenAPI definitions.

### Modified Capabilities

- `match-api` (delta): expose match findings and strengths as structured input for the tailoring analysis; no new scoring behavior, just a read-optimized endpoint or field expansion for the tailoring action to consume.
- `match-engine` (delta): document that matched strengths feed tailoring emphasis, partial matches are represented truthfully, and gaps are never fabricated into the CV. No scoring changes.

## Impact

### Backend

- **New domain**: `backend/app/Domain/Resumes/` (Actions, Data, Enums, Events, Policies, Services) — currently an empty folder structure.
- **New model**: `Resume` (Eloquent) mapped to the `resumes` table defined in `docs/database/MLD.md`.
- **New migration**: `resumes` table creation (id, candidate_profile_id FK, opportunity_id FK UNIQUE, file_id FK nullable, title, template_key, content JSON, status, generated_by, approved_at, timestamps) with indexes and unique constraint.
- **New controller**: `ResumeController` under `Http/Controllers/Api/V1/`.
- **New form requests**: StoreResumeRequest, UpdateResumeRequest, ApproveResumeRequest, PreviewResumeRequest.
- **New policies**: `ResumePolicy` with ownership scoping.
- **New API resources**: `ResumeResource`, `ResumeCollection`, `ResumePreviewResource`.
- **New services**: `TailoringAnalyzer` (section selection/reordering logic), `TailoringRewriter` (AI wording improvements with schema validation), `TailoringSchemaValidator`.
- **Reused**: `Matching` domain's `MatchAnalysis`, `MatchFinding`, `MatchScore` (read-only for tailoring input), `FingerprintService`/`StalenessService` (staleness marking), `ProfileSnapshot`/`OpportunitySnapshot`.
- **No new dependencies.**

### Frontend

- **New feature folder**: `frontend/src/features/cv-tailoring/` (api, types, composables, components, pages).
- **New page**: `TailoringWorkspacePage.vue` — step-based tailoring flow.
- **New route**: `/opportunities/:id/tailor` (lazy-loaded, `requiresAuth`).
- **Modified page**: `frontend/src/features/opportunities/pages/OpportunityDetailPage.vue` — add "Tailor CV" CTA when a completed match analysis exists.
- **Reused**: TanStack Vue Query factory conventions, UI kit (`Button`, `Card`, `Skeleton`, `Badge`, `Tabs`, `Modal`), design tokens, `extractProblemDetail`. No new dependencies.

### Database

- **New table**: `resumes` — as defined in `docs/database/MLD.md` lines 491-518. Migration includes rollback and MySQL constraint tests.

### Security and Privacy

- Every `Resume` lookup is ownership-scoped with `ResumePolicy` (cross-user 404). Route model binding is not used as authorization.
- The tailoring AI receives only the minimal delimited context (matched findings, relevant profile sections, opportunity requirements) — never passwords, cookies, tokens, or unrelated data.
- Logs redact CV content and PII. No raw provider payloads persisted beyond structured result.
- Approved resume versions are immutable; edits create new versions.
- No `v-html` for rendered resume content.

### Risks and Assumptions

- **Trust boundary**: the single most important constraint — tailoring must never invent content. AI wording improvements are proposals, not silent mutations. Every tailored claim traces to a trusted source.
- **AI failure**: if the tailoring AI is unavailable or its output fails schema validation, the system falls back to a deterministic selection (include all matched/strong findings, ordered by match score) with no wording improvements; provider failure yields a safe status with no partial update.
- **Existing route**: opportunity detail page already exists; only the "Tailor CV" CTA is added.
- **`resumes` table**: defined in MLD but never created; this change creates the migration.
- **Snapshot immutability**: once approved, a tailored CV is immutable per RES-004; re-tailoring creates a new version.

### Out of Scope

- Cover letter generation
- Application submission or auto-apply
- Interview preparation
- Automatic profile mutation from tailoring AI output
- Invented experience, skills, education, projects, certifications, languages, or achievements
- Company research or employer analysis
- General-purpose CV builder unrelated to a specific opportunity
- PDF/DOCX export (reserved for the `resume-pdf-docx-export` change, item 12)
- Resume template design or selection (templates are a string key; visual templates are future work)

### Acceptance Criteria

- AC-TAIL-01: A tailored CV can only be created from a confirmed opportunity with a completed match analysis; no creation without both.
- AC-TAIL-02: Tailoring selects, reorders, and prioritizes content exclusively from the trusted candidate profile and confirmed evidence; no invented content enters the tailored CV.
- AC-TAIL-03: AI-proposed wording changes are presented as reviewable proposals with Accept / Edit / Revert controls; no wording is applied silently.
- AC-TAIL-04: Every tailored claim is traceable to a trusted profile source (profile item, candidate skill, or evidence record); the traceability is exposed in the API response and UI.
- AC-TAIL-05: Matched strengths are emphasized, partial matches are represented truthfully, and gaps are never fabricated into the CV.
- AC-TAIL-06: The tailoring workspace shows only meaningful proposed changes; unchanged content is collapsed or hidden to avoid overwhelm.
- AC-TAIL-07: The candidate can preview the final CV before saving; the preview renders a clean, CV-focused layout.
- AC-TAIL-08: Approved tailored CVs are immutable; editing creates a new version; the version history is accessible.
- AC-TAIL-09: When the source profile or opportunity changes, existing tailored CVs are marked stale via fingerprint; staleness is visible in the API and UI.
- AC-TAIL-10: All resume resources enforce ownership policies with cross-user 404 and rate limiting; RFC 9457 problem details for validation, conflict, and not-found cases.
- AC-TAIL-11: The tailoring flow passes lint, format, vue-tsc, Vitest, build, and is verified in-browser at ~390, ~768, 1280, and 1440 px with keyboard focus and contrast compliance.
- AC-TAIL-12: AI tailoring uses fake provider by default in tests; live provider tests are opt-in, isolated, and excluded from normal CI.

### Requirement IDs

- RES-001: Generate from trusted profile data, selected analysis, and template.
- RES-002: Improve truthful content without inventing facts.
- RES-003: Candidate edits before approval.
- RES-004: Approved versions are immutable.
- TAIL-001: Tailored CV creation requires confirmed opportunity + completed match analysis.
- TAIL-002: Section selection and ordering driven by match findings and relevance scoring.
- TAIL-003: Skills prioritization based on match importance and candidate proficiency.
- TAIL-004: AI wording improvements are reviewable proposals with traceability.
- TAIL-005: Side-by-side comparison for meaningful changes.
- TAIL-006: Staleness detection when source data changes.
- TAIL-007: Version history for tailored CVs.
- TAIL-008: Mobile usable, desktop provides richer editing workspace.
