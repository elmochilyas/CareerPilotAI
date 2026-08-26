## Context

The repository has 7 archived changes delivering authentication, profile CRUD, skills with evidence, CV ingestion, opportunity ingestion, deterministic matching, and (in progress) clarification workflows. The `Resumes` domain folder exists at `backend/app/Domain/Resumes/` with empty subfolders (Actions, Data, Enums, Events, Policies, Services). The `resumes` table is defined in `docs/database/MLD.md` (lines 491-518) but no migration has been created. No Eloquent model exists yet. The frontend has no `cv-tailoring` feature folder.

Existing patterns to reuse:

- **Preview/confirm**: `Opportunities` domain's `GeneratePreviewAction` + `ConfirmOpportunityAction` (DB transaction, `lockForUpdate`, `version_token`) for immutabilization.
- **Staleness**: `Matching`'s `FingerprintService`/`StalenessService` for detecting source data changes.
- **AI boundaries**: `CvIngestion`'s `OpenAiCvAnalyzer` pattern — schema-validated structured output, fake in tests, provenance recording.
- **Ownership**: `CvDocumentPolicy` pattern — every lookup scoped by `auth()->user()`, cross-user 404.
- **Frontend features**: `clarification` feature folder pattern — api module, types, composables, components, TanStack Vue Query hooks.
- **Problem details**: `app/Support/ProblemDetails` for RFC 9457 error responses.

## Goals / Non-Goals

**Goals:**

- Create a `Resume` model and migration matching the MLD definition.
- Implement the `Resumes` domain with actions for create, update, approve, preview, and staleness detection.
- Implement a `TailoringAnalyzer` service that selects, reorders, and prioritizes content from trusted profile data based on match findings.
- Implement a `TailoringRewriter` service (AI, schema-validated) that proposes wording improvements for reviewable acceptance.
- Expose REST endpoints for resume CRUD with ownership policies.
- Build a frontend tailoring workspace with step-based review, side-by-side comparison, and preview.
- Support version history and staleness detection.
- Maintain the trust boundary: no invented content, AI proposals are reviewable, approved CVs are immutable.

**Non-Goals:**

- PDF/DOCX export (reserved for `resume-pdf-docx-export`, change 12).
- Cover letter generation.
- Application submission or auto-apply.
- Interview preparation.
- Resume template visual design (templates are a string key; visual templates are future work).
- General-purpose CV builder unrelated to a specific opportunity.
- New dependencies or infrastructure.

## Decisions

### D1. Resumes domain owns the tailoring workflow

A new `backend/app/Domain/Resumes/` domain (Actions, Data, Enums, Events, Policies, Services) implements the tailoring workflow. The domain consumes `Matching` (read-only: findings, scores, staleness) and `Profile`/`Skills` (read-only: trusted data). It produces `Resume` records.

**Why:** one owning domain per capability, clear boundary between "matching output" and "resume construction", consistent with the existing domain architecture. Alternatives considered: (a) extending `Matching` — rejected, would bloat matching with resume-write responsibilities; (b) extending `CvIngestion` — rejected, ingestion is about importing existing CVs, not generating new ones from profile data.

### D2. Single `resumes` table per MLD with content JSON

The `resumes` table follows the MLD definition exactly:

```text
resumes
  id                      (ulid PK)
  candidate_profile_id    (FK -> candidate_profiles.id, indexed)
  opportunity_id          (FK -> job_opportunities.id, UNIQUE)
  file_id                 (FK -> files.id, nullable)
  title                   (string(255))
  template_key            (string(100), nullable)
  content                 (json)          # structured CV content
  status                  (string(30))    # draft | approved
  generated_by            (string(30))    # ai | manual
  approved_at             (datetime, nullable)
  version_no              (smallint unsigned)  # auto per opportunity
  profile_snapshot        (json)          # snapshot of profile at creation
  opportunity_snapshot    (json)          # snapshot of opportunity at creation
  match_snapshot          (json)          # snapshot of match analysis at creation
  ai_metadata             (json, nullable) # AI provenance
  created_at, updated_at
```

`content` is a JSON column containing the structured resume: `sections[]` with `type`, `title`, `items[]` (each item has `source_ref`, `original_text`, `current_text`, `ai_proposals[]`). The `file_id` column is reserved for PDF/DOCX export (change 12) and remains nullable in this change. `version_no` auto-increments per `(candidate_profile_id, opportunity_id)`.

**Why:** MLD is the authority for table design. JSON content is appropriate here because it is a bounded, versioned, self-contained document not heavily queried at the item level. Snapshots ensure reproducibility even when the source profile changes. Alternatives considered: (a) fully normalized content tables — rejected for MVP, would add significant migration/query complexity with no query benefit; (b) no snapshots — rejected, violates RES-001's reproducibility requirement.

### D3. TailoringAnalyzer: deterministic content selection and ordering

`TailoringAnalyzer` is a pure PHP service (no AI) that:

1. Loads the candidate's trusted profile: `CandidateProfile`, all `ProfileItem` records, all `CandidateSkill` records (state in `verified`, `claimed`), and associated evidence.
2. Loads the `MatchAnalysis` with its `MatchFinding` records and `MatchScore` records.
3. Builds a relevance score per profile entity: `matched` required = 1.0, `matched` preferred = 0.8, `partial` = 0.5, `claimed` with no finding = 0.2, `learning` = 0.1.
4. Selects sections: always include summary, experience, skills, education. Include projects if any are relevant. Include certifications, languages only if relevant.
5. Orders sections by aggregate relevance score (skills and experience first when match is strong on those).
6. Within experience/projects: order by relevance score, include top 5 relevant + 2 most recent non-relevant, omit rest.
7. Within skills: order by (importance, match_state, evidence_count).
8. Produces a `TailoringResult` DTO with the structured content and traceability refs.

**Why:** deterministic selection guarantees the trust boundary — no AI needed for what to include or how to order. The AI role is limited to wording improvements on already-selected content. Alternatives considered: (a) AI-driven selection — rejected, risks inventing content and violates explainability; (b) candidate manual selection — acceptable but poor UX for MVP, can be added later.

### D4. TailoringRewriter: AI wording proposals with strict boundaries

`TailoringRewriter` is an AI service (Laravel AI SDK, schema-validated) that:

1. Receives the selected content from `TailoringAnalyzer` (only items with `relevance >= 0.5`).
2. For each bullet/description, proposes wording improvements: clearer language, better emphasis on relevant skills, professional tone, ATS-friendly phrasing.
3. Must NOT add factual claims, change dates, invent metrics, or alter the meaning of the content.
4. Output passes a strict schema validator: each proposal has `source_ref`, `original_text`, `proposed_text`, `change_type` (reword/emphasize/reorder), and `confidence`.
5. Proposals with `change_type = reorder` affect section/item ordering, not text content.
6. On provider failure or schema validation failure: the deterministic content from `TailoringAnalyzer` is used as-is with `ai_metadata.fallback_reason` recorded.
7. AI provenance is recorded: provider, model, prompt version, latency, tokens, status.

**Why:** RES-002 allows improving truthful content. Schema validation prevents hallucinated proposals. The fallback to deterministic content ensures reliability. Alternatives considered: (a) no AI at all — acceptable fallback, and the system works without it; (b) AI for selection — rejected, too risky for trust boundary.

### D5. Resume model and state machine

```text
ResumeStatus: draft | approved
```

State machine:
- `draft` → `approved` (via approve action, sets `approved_at`)
- `approved` → immutable (no transitions; editing creates a new draft)

Invalid transitions return 409 `resume_immutable`. The approve action runs in a DB transaction with `lockForUpdate` on the resume row.

**Why:** RES-004 requires immutability. The simple two-state machine is sufficient for MVP. Later versions may add `archived` or `exported` states.

### D6. Staleness via snapshots and fingerprints

Each `Resume` stores `profile_snapshot`, `opportunity_snapshot`, and `match_snapshot` as JSON at creation time. When the candidate requests a resume, the system compares current `CandidateProfile.updated_at` against the snapshot's `profile_updated_at` and current `MatchAnalysis` fingerprint against the snapshot's `match_fingerprint`. If either differs, `stale = true` is returned.

**Why:** snapshots make the resume self-contained and reproducible (RES-001). Fingerprint comparison is the existing staleness mechanism from `Matching`. Alternatives considered: (a) real-time staleness via event listeners — rejected for MVP, adds complexity; (b) no staleness — rejected, violates RES-001.

### D7. API surface (additive, all ownership-scoped)

```text
POST   /api/v1/opportunities/{opportunity}/resumes    → 201 created
GET    /api/v1/opportunities/{opportunity}/resumes    → 200 list
GET    /api/v1/resumes/{resume}                       → 200 detail
PATCH  /api/v1/resumes/{resume}                       → 200 updated
POST   /api/v1/resumes/{resume}/approve               → 200 approved
GET    /api/v1/resumes/{resume}/preview               → 200 preview
DELETE /api/v1/resumes/{resume}                       → 204 deleted
```

- All routes behind `auth:sanctum` + CSRF.
- All lookups scoped by `ResumePolicy` with cross-user 404.
- Errors: RFC 9457 via `app/Support/ProblemDetails`.
- Rate limiting: general-write (60/min/user) for create/update/approve/delete; general-read (120/min/user) for list/show/preview.
- Create is synchronous (AI wording proposals are generated inline, not queued, for MVP — the content is small and the AI call is fast). Future: queue for larger payloads.

**Why:** follows the existing API conventions from `CvDocumentController` and `MatchAnalysisController`. The opportunity-scoped create route makes the ownership chain explicit.

### D8. Frontend: tailoring workspace with step-based flow

- New feature folder: `frontend/src/features/cv-tailoring/` (api, types, composables, components, pages).
- New route: `/opportunities/:id/tailor` (name `opportunities-tailor`, lazy-loaded, `requiresAuth`).
- Modified route: opportunity detail page gains a "Tailor CV" CTA.
- Flow: Step 1 (Create) → Step 2 (Review changes) → Step 3 (Preview) → Step 4 (Save).
- Step 2 uses `TailoringChangeCard` components with Accept/Edit/Revert per change.
- Side-by-side diff for reworded content at desktop widths.
- Collapsed sections for unchanged content.
- Design system: tokens, UI kit, calm/premium aesthetic per `CAREERPILOT_PREMIUM_DESIGN_SYSTEM.md`.

**Why:** step-based flow matches the user's request for a simple, premium experience. Progressive disclosure avoids overwhelm. Dedicated route gives the flow its own loading/error/empty handling.

### D9. No new dependencies

Everything uses existing Laravel (validation, policies, queue, DB, AI SDK, `app/Support/ProblemDetails`) and existing frontend tooling (TanStack Vue Query, UI kit, design tokens, Tailwind). The AI rewriter, when active, uses the Laravel AI SDK with a faked provider in default tests.

### D10. Security, privacy, and observability

- **Ownership**: every lookup from `auth()->user()`; policies in controllers; route binding is not authorization. Cross-user tests prove guessed IDs 404.
- **Privacy**: the AI rewriter prompt receives only the delimited content to be reworded and the job requirements context — never passwords, cookies, tokens, or unrelated profile data. Logs redact CV content and PII. No raw provider payloads persisted beyond structured result.
- **Prompt injection**: profile content and job descriptions are untrusted data, delimited and instruction-ignored; AI output passes strict schema + business validation before use.
- **Quotas**: resume writes respect the general-write rate limit; AI metadata recorded per AI-006.
- **Observability**: request IDs propagate to jobs; metrics for tailoring count, approval rate, AI fallback rate, and stale-resume rate.

## Risks / Trade-offs

- **Trust boundary violation** (AI inventing content) → [Risk] Mitigation: the `TailoringAnalyzer` is deterministic and never invents; the `TailoringRewriter` is schema-validated and only proposes wording changes on already-selected content; approved proposals are immutable; architecture tests assert no code path can add content not traceable to trusted sources.
- **AI wording quality** (proposals not useful or too aggressive) → [Risk] Mitigation: the candidate reviews every proposal; Edit/Revert controls are available; fallback to no AI wording if provider fails; evaluation gate requires schema validity >= 99% and zero hallucinated facts.
- **Stale resumes after profile edit** → [Risk] Mitigation: fingerprint comparison on every read; staleness flag in API response; UI warns and offers re-tailor; tests assert staleness detection.
- **Single-table JSON content** → [Risk] Mitigation: content is bounded and self-contained; no cross-item queries needed for MVP; can normalize later if query patterns demand it.
- **Synchronous AI in create** → [Risk] Mitigation: content is small (one page of bullets); AI call is fast; queue can be added later if latency becomes an issue; timeout and retry are configured.

## Migration Plan

1. Add migration for `resumes` table with FKs, indexes, unique `(opportunity_id)`, and JSON columns; include rollback (drop table).
2. Add migration for `match_findings.tailoring_relevance` column (string(10), nullable, computed) with rollback.
3. Deploy backend first (new domain, routes, policies, migration) — no breaking API changes.
4. Deploy frontend build with new feature folder and route.
5. Rollback: reverse migrations restore prior schema; the tailoring feature is additive so older frontend still works without it.

## Open Questions

- Should the `content` JSON schema be formally defined as a PHP class (e.g., `ResumeContent` DTO) or remain as an array? Decision: define as a typed DTO for validation and clarity; confirm with reviewers.
- Should version history show all versions or only approved versions by default? Decision: show all, with a filter for approved only; confirm with reviewers.
- Should the "Tailor CV" button on opportunity detail show a count of existing versions? Decision: yes, show "(2 versions)" when versions exist; confirm with reviewers.
- Should re-tailoring from a stale CV delete the old draft or keep it? Decision: keep it; the old draft is a record of what was attempted; confirm with reviewers.
