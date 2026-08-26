# Design: core-hardening-baseline (Phase A)

## Context

The CareerPilot core is implemented and stable. This change hardens audited gaps without rebuilding stable systems. Verified current state (from code inspection):

- `LoginUserAction` blocks only `UserAccountStatus::Suspended`; `Disabled` falls through to a successful login. No middleware rejects sessions of non-active accounts.
- CandidateSkill routes scope all lookups to the authenticated user's profile inside domain actions (cross-user → 404, covered by `tests/Feature/Api/V1/Skills/AuthorizationTest.php`), but the `CANDIDATE-SKILLS-009` "policy checks" requirement has no actual policy class; evidence update/remove lack cross-user tests.
- `ProfileDuplicateController` validates inline; item merge lacks `distinct` and an explicit keep∉duplicates rule at the HTTP layer (the action re-checks), skills merge lacks `allow_possible`, languages endpoint is loosely validated. Ownership is correctly server-derived today.
- Resume versioning: migration `2026_08_24_235959_fix_resumes_unique_for_versioning` removed the global unique on `job_opportunity_id` (reversible). `CreateResumeAction` hardcodes `'version_no' => 1`. `ResumeResource` already exposes `version_no`, `stale`, `stale_reason`.
- Staleness: `ApproveResumeAction` returns 409 `resume_stale` with the required message, but computes staleness via `TailoringAnalyzer::computeStaleness()` against the **latest MatchAnalysis**, not the resume's own snapshots. `TailorResumeAction` refreshes only `content`/`generated_by`, never the resume's snapshot fingerprints.
- Tailoring relevance: `TailoringAnalyzer::analyzeRelevance()` prefers `MatchFinding.tailoring_relevance` and otherwise uses a deterministic fallback (`TextNormalizer::similarity`, `ProfileIdentityService`, opportunity skills/requirements) with a low-relevance floor. Existing tests: `TailoringAnalyzerTest`.
- Clarification: `ApplyProposalAction` applies only `candidate_skill` targets through Skills actions in one transaction; other target types throw 422 `proposal_not_supported`. Only one audit write exists (`WriteClarificationAuditEventListener` for `ProposalAccepted`); `clarification_audit_events` has no event-kind discriminator column.
- Frontend: router `/` renders `features/home/pages/HomePage.vue` (queries profile/CV/ingestions/skills with skeleton+empty handling but **zero** error/retry states). `features/dashboard/pages/DashboardPage.vue` is a 0-byte dead file not referenced by any route. `nextRequestId()` is duplicated in `features/matching|clarification|cv-tailoring/api/index.ts`.
- Database docs (`docs/database/MCD.md`, `MLD.md`) still model a generic `FILE` entity (`USER ||--o{ FILE`, `RESUME ||--o| FILE`, `APPLICATION_ACTIVITY ||--o| FILE`) and `resumes.file_id` exists as a nullable column with no `files` table behind it. Implementation reality is purpose-specific storage (`cv_documents`). Interviews/tasks are planned as `tasks.type ∈ {task, reminder, interview, follow_up}`.

## Goals / Non-Goals

**Goals**

- Close each audited gap with the smallest coherent change that satisfies the delta specs.
- Keep every existing stable behavior green (existing test suites must pass unmodified except where this change explicitly changes documented behavior).
- Produce two durable architecture decisions (file storage, interview model) as documentation before dependent phases start.

**Non-Goals**

- No rebuild of auth architecture, ProfileIdentityService, skills state machine, ingestion pipelines, match math, or PDF export internals.
- No Company Research / Applications / Tasks / Interviews / Roadmap / Notifications / DOCX / Admin / quotas / export-deletion implementation.
- No generic files table, no application documents schema, no interview tables in Phase A.
- No dependency additions or upgrades.

## Decisions

### D1 — Disabled accounts: reject at login + central session rejection

- Login: after credential verification succeeds, reject `account_status = disabled` by throwing a `ProblemDetailsException(403, …, 'account_disabled')` so the existing Problem Details renderer emits the exact contract. Suspended keeps its current distinct message/behavior (documented in AUTH-001 spec).
- Sessions: add an `EnsureActiveAccount` middleware on the authenticated (`auth:sanctum`) route group. It compares the session user's `account_status` and throws the same 403 `account_disabled` problem when the status is not `active`. `/api/v1/me` stays inside the group — it must return the 403 problem too, which is exactly how the SPA learns to sign out cleanly; no special-casing needed because the renderer output is machine-readable.
- Anti-enumeration preserved: disabled check runs strictly after `Hash::check` success; unknown-email/wrong-password paths unchanged.
- Alternatives considered: (a) rejecting only at login — rejected because stale sessions would keep working until expiry; (b) revoking Sanctum tokens on disable — rejected because there is no admin disable workflow yet and event-driven revocation belongs to the future account-lifecycle change.

### D2 — CandidateSkill authorization: real policy, preserve 404 semantics

- Add `App\Domain\Skills\Policies\CandidateSkillPolicy` with `view/update/archive/restore/delete/addEvidence/updateEvidence/removeEvidence` methods that verify the skill's `candidate_profile_id` belongs to the requesting user's profile. Register it and call `$this->authorize(...)` from `CandidateSkillController` as defense-in-depth.
- Preserve established cross-user 404 semantics (spec-pinned): the controller resolves the skill through the profile-scoped actions first (404 for foreign ids); the policy guards against future call sites bypassing scoping. Policy denials map to the existing `forbidden` rendering; they should be unreachable through public routes, which is asserted by tests.
- Add the missing regression tests for `updateEvidence`/`destroyEvidence` cross-user access.
- Alternative: switch cross-user responses to 403 — rejected; CANDIDATE-SKILLS-009 and shipped tests pin 404.

### D3 — Cleanup validation: dedicated Form Requests

- Create `app/Http/Requests/Api/V1/Profile/CleanupItemsRequest`, `CleanupSkillsRequest`, `CleanupLanguagesRequest`. Rules implement the PDC-002/003 contract: `keep_id` required|int|exists scoped later by ownership; `duplicate_ids` required|array|min:1|distinct with integer elements; `exclude_with:duplicate_ids`-style rule preventing `keep_id ∈ duplicate_ids` (Laravel `Rule::notIn` / closure); `allow_possible` sometimes|boolean.
- Controller keeps deriving `$profile` from `$request->user()` (never body input) and passes it to actions; action-level checks remain as second line of defense.
- Error shape unchanged: ValidationException already renders RFC 9457 `validation_error` with `errors`.

### D4 — Resume versioning: per-opportunity increment under lock

- In `CreateResumeAction`, inside the existing transaction that already locks candidate drafts: compute `version_no = (max version_no for profile+opportunity) + 1` instead of hardcoding 1.
- Do **not** add a DB unique on `(candidate_profile_id, job_opportunity_id, version_no)` in Phase A: multiple parallel draft creation is already prevented by the existing-draft lock, and adding a composite unique would need a backfill decision for pre-versioning rows. Revisit if a later change introduces concurrent multi-draft creation. (No unsafe global uniqueness anywhere.)
- Ordering: `ListResumesAction` orders by `version_no` desc (verify; fix if needed).
- Migration reversibility/existing data: no new migration required; the existing fix migration's `down()` restores the previous state and all rows keep `version_no = 1` semantics.

### D5 — Staleness anchored to the resume's own snapshots

- Change draft staleness computation to compare the resume's stored `profile_snapshot.fingerprint` / `opportunity_snapshot.fingerprint` against fresh `FingerprintService` computations of the current profile/opportunity (reuse `StalenessService` logic; extract a resume-scoped variant rather than duplicating fingerprint rules).
- `ApproveResumeAction` uses this resume-scoped staleness; message/code stay exactly `resume_stale` + approved wording.
- `CreateResumeAction` already stores fresh fingerprints at creation. `TailorResumeAction` and `UpdateResumeContentAction` refresh both snapshots after content regeneration, clearing staleness deterministically ("regenerate before approve" loop).
- `MatchAnalysis`-based staleness remains untouched for match surfaces (brief page, recalculate prompts).
- Trade-off: pre-existing drafts generated under the old model may flip to stale until regenerated; accepted because MVP data is local/test and the new rule matches the approved product intent.

### D6 — Tailoring relevance: audit-and-pin, no rewrite

- Keep the two-path design (explicit `tailoring_relevance` findings first, deterministic fallback second). Harden the fallback floor so sparse signals never yield empty CV content (summary + top trusted items always present when profile has them).
- Lock behavior with unit tests: identical inputs → identical scores; no fabricated source ids (fallback references only real profile items/candidate skills/profile id for languages); traceability fields preserved into resume content metadata.

### D7 — Clarification: audit events now, ProfileItem apply deferred unless trivially supported

- Add a nullable `event` string column to `clarification_audit_events` (small migration; append-only table, rollback drops nothing but the column). Write events from existing actions/listeners using one shared writer service (best-effort try/catch like the current listener): `question_created`, `answer_submitted`, `proposal_generated`, `proposal_accepted`, `proposal_rejected`, `question_skipped`, `proposal_apply_failed`. Metadata stays structured (ids, field, reason codes); no provider payloads/secrets.
- Target-scope extension (experience/education/language/basic fields): audit `BuildProposalAction`/question templates during implementation. Applying ProfileItem proposals requires trusted mutation paths with provenance/concurrency/completion updates that do not exist today — **defer** the extension and record the limitation in CLAR-012/design docs rather than force it into Phase A. If the audit unexpectedly shows full structural support, deliver it as an explicitly listed task set with its own tests; otherwise document-only.

### D8 — Dashboard: delete dead file, harden HomePage panels

- Delete the 0-byte `features/dashboard/pages/DashboardPage.vue` (unreferenced). HomePage remains the canonical dashboard at `/`.
- Introduce a small reusable panel-error pattern (local component or composable using Vue Query's `isError`/`refetch`) applied per query on HomePage: error banner + retry button per failed panel, keeping skeletons/empties for the rest. One failed request can never blank the page.
- No new widgets beyond existing core queries; resumes/matching state surfaced from existing endpoints where cheaply available.

### D9 — X-Request-ID: single source in API client layer

- Move `nextRequestId()` into `src/api/client/request-id.ts` and attach the header via an Axios request interceptor in the centralized client. Feature modules stop importing local copies (delete duplicated implementations). Response-side `request_id` exposure continues through the existing Problem Detail mapper — no feature-level changes needed.

### D10 — File storage architecture: Option B (purpose-specific entities)

**Decision:** formally adopt purpose-specific document/storage entities and abandon the generic FILE model.

- Why: the implemented reality is already Option B (`cv_documents` owns upload lifecycle, scan status, extraction linkage, retention); exports are self-describing artifacts tied to their aggregate (resume/application); a generic FILE entity would force polymorphic ownership/provenance columns onto every consumer while none of the planned consumers (application documents, PDF/DOCX exports, account export bundles) share meaningful structure beyond bytes+mime. Ownership/provenance are stronger when modeled per purpose (e.g., application documents FK directly to application + uploader + scan status).
- Migration implications: no data migration now; `resumes.file_id` is a dangling nullable column referencing a table that will never exist. Plan its removal in the next change that touches `resumes` schema (Application Documents phase), not in Phase A. Docs update: mark FILE deprecated in MCD/MLD with a note pointing to purpose-specific replacements; README records the ADR-style decision.
- Future needs covered without FILE: PDF/DOCX exports store artifact metadata on their owning rows (as today); account export/deletion walks purpose-specific tables; quarantine/scan-status abstraction is reused per entity.

### D11 — Interview model: recommend Option C (task + dedicated interview record)

**Recommendation for Phase D:** a task/reminder entry handles scheduling/notification concerns (due date, completion, notification hooks) while a dedicated `interviews` record owns interview-specific lifecycle (stage/type, scheduled time, interviewer/contact, notes, feedback, outcome).

- Rationale: interview history is analytical data (stages, outcomes, feedback quality) that outlives any reminder; overloading `tasks.type=interview` would bury lifecycle transitions in JSON metadata and pollute task lists. Option A alone loses the natural reminder surface; Option B conflates domains; C keeps both clean with a clear 1:0..1 link (task may reference its interview).
- Documented now in design/docs/database updates; final schema work happens in the tasks/interviews phase.

## Risks / Trade-offs

- [Central non-active rejection could log users out en masse if statuses are ever mass-flipped] → Statuses only change through explicit future workflows; middleware rejects only non-active states; tests pin active-user passthrough.
- [Moving staleness anchor flips old drafts to stale] → Accepted; regeneration clears it; aligns with approved intent; no production data at MVP stage.
- [Policy added under existing 404 flow could mask bugs silently] → Cross-user tests extended to every route including evidence sub-resources.
- [Audit writer best-effort could drop events silently] → Warning logs include request_id/proposal_id; schema test asserts events persist under normal operation.
- [Deleting DashboardPage.vue touches nothing functional] → Grep confirms zero imports; deletion verified by build.

## Migration Plan

1. Backend: add identity middleware + login guard (no schema change).
2. Backend: CandidateSkill policy + controller wiring (no schema change).
3. Backend: cleanup Form Requests swap (no schema change).
4. Backend: resume version increment + snapshot-refresh + approval check refactor (no schema change).
5. Backend: `clarification_audit_events.event` column migration (additive, reversible via dropColumn; forward-safe because writer tolerates missing values during rolling deploys — single-node MVP makes this moot).
6. Frontend: request-id centralization → dashboard hardening → dead-file deletion.
7. Docs: MCD/MLD/README updates for storage + interview decisions.
8. Rollback: each step independently revertible via git; the only schema step rolls back with `down()` dropping the added column.

## Open Questions

None blocking. The clarification ProfileItem apply extension is deliberately resolved as "defer with documentation" (D7) pending the in-task audit; if that audit contradicts the assumption, the change artifacts get updated before implementation proceeds.
