## 1. Baseline Inspection

- [x] 1.1 Inspect existing `Matching` domain actions, `MatchAnalysis`, `MatchFinding`, `MatchScore` models, `FingerprintService`, `StalenessService`, and the `match_analyses`/`match_findings` schema; confirm fields for tailoring input.
- [x] 1.2 Inspect `Profile` domain actions, `CandidateProfile`, `ProfileItem` models and schema to define trusted data source entry points.
- [x] 1.3 Inspect `Skills` domain actions, `CandidateSkill` model (state, evidence) to define skill inclusion rules.
- [x] 1.4 Inspect `CvIngestion` domain's `OpenAiCvAnalyzer` and `CvAnalysisSchemaValidator` for AI service patterns to reuse.
- [x] 1.5 Inspect `Opportunities` `ConfirmOpportunityAction` and the `JobOpportunity` model for the opportunity-scoped create pattern.
- [x] 1.6 Inspect `app/Support/ProblemDetails` for RFC 9457 error response patterns.
- [x] 1.7 Inspect existing frontend feature folder patterns (especially `clarification/`) for api, types, composables, components conventions.
- [x] 1.8 Inspect `docs/database/MLD.md` `resumes` table definition (lines 491-518) and confirm all columns/contraints.
- [x] 1.9 Run baseline backend and frontend quality gates and record the starting state.

## 2. Database

- [x] 2.1 Create the `resumes` migration (id ULID PK, candidate_profile_id FK, opportunity_id FK UNIQUE, file_id FK nullable, title, template_key, content JSON, status, generated_by, approved_at, version_no, profile_snapshot JSON, opportunity_snapshot JSON, match_snapshot JSON, ai_metadata JSON nullable, timestamps; index FKs and `(candidate_profile_id, status)`).
- [x] 2.2 Create the `tailoring_proposals` migration (id ULID PK, resume_id FK, source_type, source_id, original_text, proposed_text, change_type, status, edited_text nullable, accepted_at nullable, ai_metadata JSON nullable, timestamps; index FKs and `(resume_id, status)`).
- [x] 2.3 Add `tailoring_relevance` column to `match_findings` table (string(10), nullable, default null) with rollback.
- [x] 2.4 Add Eloquent models (`Resume`, `TailoringProposal`), relationships, and factories with states (`draft`, `approved`).
- [x] 2.5 Add MySQL constraint tests (unique opportunity_id, FK ownership, status enum strings, version_no auto-increment per opportunity) and verify migration rollback.

## 3. Backend Domain

- [x] 3.1 Add enums: `ResumeStatus` (`draft`, `approved`), `TailoringProposalStatus` (`proposed`, `accepted`, `rejected`), `TailoringChangeType` (`reword`, `reorder`, `include`, `exclude`).
- [x] 3.2 Add data DTOs: `ResumeData`, `ResumeContentData`, `ResumeSectionData`, `ResumeItemData`, `TailoringProposalData`, `TailoringResult`.
- [x] 3.3 Implement `TailoringAnalyzer` service: load trusted profile/skills/evidence, load match findings, compute relevance scores per entity, select sections, order content, produce `TailoringResult` with traceability refs.
- [x] 3.4 Implement `TailoringRewriter` service (Laravel AI SDK): receive selected content, propose wording improvements, schema-validated output, fallback to deterministic content on failure, record AI provenance.
- [x] 3.5 Implement `TailoringSchemaValidator`: validate AI proposals against schema, reject proposals with factual additions, ensure all proposals have `source_ref`.
- [x] 3.6 Implement `CreateResumeAction`: validate prerequisites (confirmed opportunity + completed match analysis), create snapshots, run `TailoringAnalyzer`, run `TailoringRewriter`, persist `Resume` + `TailoringProposal` records in DB transaction.
- [x] 3.7 Implement `UpdateResumeAction`: validate status `draft`, apply content edits from candidate, update proposals status, persist in transaction.
- [x] 3.8 Implement `ApproveResumeAction`: validate status `draft`, set `status = approved`, `approved_at = now()`, lockForUpdate, persist in transaction. Return 409 `resume_immutable` for approved resumes.
- [x] 3.9 Implement `PreviewResumeAction`: load resume, apply accepted proposals to content, return finalized structured content.
- [x] 3.10 Implement `DeleteResumeAction`: validate status `draft`, delete (soft or hard per policy).
- [x] 3.11 Implement `ListResumesAction`: list resumes for an opportunity, ordered by `version_no` desc, ownership-scoped.
- [x] 3.12 Implement `ShowResumeAction`: load single resume with full content and proposals, ownership-scoped, compute staleness.
- [x] 3.13 Implement staleness computation: compare current `CandidateProfile.updated_at` against snapshot, compute `stale` boolean and `stale_reason`.
- [x] 3.14 Implement `ResumePolicy`: `viewAny`/`view` require ownership, `create` requires opportunity ownership, `update`/`delete`/`approve` require ownership + status `draft`.

## 4. Backend API

- [x] 4.1 Add routes: `POST /api/v1/opportunities/{opportunity}/resumes`, `GET /api/v1/opportunities/{opportunity}/resumes`, `GET /api/v1/resumes/{resume}`, `PATCH /api/v1/resumes/{resume}`, `POST /api/v1/resumes/{resume}/approve`, `GET /api/v1/resumes/{resume}/preview`, `DELETE /api/v1/resumes/{resume}` under auth + CSRF + rate limiting.
- [x] 4.2 Add Form Requests: `StoreResumeRequest`, `UpdateResumeRequest`, `ApproveResumeRequest` with allowlist validation.
- [x] 4.3 Add thin `ResumeController` returning API Resources; never return models directly.
- [x] 4.4 Add `ResumeResource` and `ResumeCollection` with content, proposals, staleness, and version fields.
- [x] 4.5 Map domain exceptions to stable RFC 9457 problem codes (`resume_not_found`, `resume_immutable`, `tailoring_prerequisites_not_met`, `tailoring_ai_failed`).
- [x] 4.6 Update OpenAPI 3.1 with all resume endpoints, request/response schemas, and problem codes.
- [x] 4.7 Add `tailoring_relevance` field to `MatchFindingResource` and `tailorable` field to `MatchAnalysisResource`.

## 5. Backend Tests

- [x] 5.1 Feature tests: create resume (prerequisites met, missing opportunity, missing match analysis, cross-user 404, unauthenticated 401, rate limit 429).
- [x] 5.2 Feature tests: list/show resumes (ownership scoping, version ordering, staleness computation).
- [x] 5.3 Feature tests: update draft resume (content edits, proposal status changes, approved resume rejected with 409).
- [x] 5.4 Feature tests: approve resume (sets status/approved_at, immutabilize, double-approve rejected).
- [x] 5.5 Feature tests: preview resume (applies accepted proposals, returns finalized content).
- [x] 5.6 Feature tests: delete draft resume (deleted, approved resume rejected with 409).
- [x] 5.7 Unit tests: `TailoringAnalyzer` (relevance scoring, section selection, ordering, skill prioritization, experience truncation, rejected/archived exclusion).
- [x] 5.8 Unit tests: `TailoringRewriter` (fake by default, schema validation, fallback on provider failure, no factual additions).
- [x] 5.9 Security tests: cross-user 404 for all resume endpoints, CSRF enforcement, BOLA with guessed IDs.
- [x] 5.10 Integration tests: MySQL unique constraint on opportunity_id, version_no auto-increment, migration rollback.
- [x] 5.11 Architecture tests: no code path can add content not traceable to trusted sources; domain boundaries respected.

## 6. Frontend

- [x] 6.1 Create `frontend/src/features/cv-tailoring/` (api module, types, TanStack Vue Query hooks following existing factory conventions).
- [x] 6.2 Add route `/opportunities/:id/tailor` (lazy-loaded, `requiresAuth`) to the router.
- [x] 6.3 Implement `TailoringWorkspacePage.vue` with step-based flow (Create → Review → Preview → Save).
- [x] 6.4 Implement `TailoringChangeCard` component with Accept/Edit/Revert controls for wording proposals.
- [x] 6.5 Implement side-by-side diff view for reworded content at desktop widths.
- [x] 6.6 Implement collapsed sections for unchanged content with disclosure toggle.
- [x] 6.7 Implement preview step with clean CV-focused layout.
- [x] 6.8 Implement save/approve and save-as-draft actions with confirmation and redirect.
- [x] 6.9 Add "Tailor CV" CTA to `OpportunityDetailPage.vue` (visible when match analysis is completed).
- [x] 6.10 Implement state handling: loading, empty (no profile data), error-with-retry, stale warning, success confirmation.
- [x] 6.11 Apply design system (tokens, UI kit, calm/premium aesthetic) and ensure responsive layouts at ~390, ~768, 1280, 1440 px.
- [x] 6.12 Ensure accessibility: semantic HTML, keyboard navigation, visible focus, labels, contrast (WCAG 2.2 AA), screen-reader announcements, `prefers-reduced-motion`, no `v-html`.

## 7. Frontend Tests and Verification

- [ ] 7.1 Vitest: step flow navigation, change card accept/edit/revert, preview rendering, save/approve, empty/error/stale states.
- [ ] 7.2 Vitest: accessibility assertions (labels, focus, announcements) and no-`v-html` check.
- [x] 7.3 Run `npm run format`, `npm run lint`, `npm run test:unit -- --run`, `npm run build`, and the configured type-check.
- [ ] 7.4 Start dev server and verify the tailoring flow in-browser (mobile/tablet/desktop), checking console/network and keyboard/contrast.

## 8. Documentation and Integration

- [ ] 8.1 Update `docs/database/MLD.md` to add `resumes` and `tailoring_proposals` table definitions and remove any "future resume" deferral notes.
- [ ] 8.2 Update `docs/database/MCD.md` to add the tailoring proposal entity if needed.
- [ ] 8.3 Add or update an ADR capturing the tailoring trust boundary, deterministic-analyzer-first selection, AI rewriter boundaries, and snapshot-based staleness decisions.
- [ ] 8.4 Update README/env example if they reference resume endpoints or the change.

## 9. Quality Gates and Finalization

- [x] 9.1 Run backend gates: `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, and the configured PHPStan/Larastan command.
- [x] 9.2 Run frontend gates: format, lint, type-check, unit tests, and production build.
- [x] 9.3 Verify `php artisan migrate:status` shows applied resume migrations.
- [ ] 9.4 Run `/opsx:verify` for the change and resolve all critical findings.
- [ ] 9.5 Record completion evidence: files changed, migrations, commands executed, tests passed, remaining risks, and manual verification.

## 10. Tailoring workspace route regression

- [x] 10.1 Align frontend resume list/create requests with the canonical opportunity-scoped endpoints
- [x] 10.2 Add the missing first-resume creation state and keep load failures retryable in place
- [x] 10.3 Redirect an expired protected SPA session to login while preserving the tailoring destination
- [x] 10.4 Persist generated trusted content and proposals, normalize preview data, and cover empty/processing/failure/ready renderer states
