# Tasks: core-hardening-baseline (Phase A)

## 1. Disabled account authentication protection

- [x] 1.1 Add `account_disabled` rejection to `LoginUserAction` after credential verification (`ProblemDetailsException` 403, code `account_disabled`), keeping `Suspended` behavior unchanged
- [x] 1.2 Add `EnsureActiveAccount` middleware rejecting authenticated requests from non-active accounts with 403 `account_disabled`; register it on the `auth:sanctum` route group in `routes/api.php`
- [x] 1.3 Extend `tests/Feature/Api/V1/Auth/LoginTest.php`: disabled account cannot log in (403 `account_disabled`), wrong-password for disabled account stays generic 422, active login unaffected, suspended case still green
- [x] 1.4 Add feature test: request with a valid session of a disabled account returns 403 `account_disabled`; active and suspended-session cases covered; `/api/v1/me` returns the parseable problem
- [x] 1.5 Verify password reset + email verification flows unaffected (existing suites pass; add explicit disabled-account forgot-password test asserting no enumeration leak)

## 2. CandidateSkill authorization policy

- [x] 2.1 Create `App\Domain\Skills\Policies\CandidateSkillPolicy` with view/update/archive/restore/delete/addEvidence/updateEvidence/removeEvidence ownership methods; register it for authorization
- [x] 2.2 Wire `$this->authorize(...)` into `CandidateSkillController` for each route while preserving profile-scoped 404 cross-user semantics
- [x] 2.3 Extend `tests/Feature/Api/V1/Skills/AuthorizationTest.php`: cross-user `updateEvidence` and `destroyEvidence` cases; assert all routes return 404 for foreign skill ids and that owner flows still pass

## 3. Profile cleanup endpoint validation

- [x] 3.1 Create `CleanupItemsRequest`, `CleanupSkillsRequest`, `CleanupLanguagesRequest` Form Requests implementing the PDC-002/003 rules (`keep_id`, distinct non-empty `duplicate_ids`, keepâˆ‰duplicates, boolean `allow_possible`) and swap them into `ProfileDuplicateController`
- [x] 3.2 Keep profile ownership derived from the authenticated user only; confirm actions retain their transactional ownership re-checks
- [x] 3.3 Extend `tests/Feature/Profile/DuplicateCleanupTest.php`: malformed merge payload (missing/empty/non-distinct `duplicate_ids`), `keep_id` inside `duplicate_ids`, non-boolean `allow_possible`, cross-user ids rejected, client-supplied profile id ignored; assert RFC 9457 shape (`code`, `errors`, `request_id`)

## 4. Resume versioning finalization

- [x] 4.1 In `CreateResumeAction`, compute next `version_no` as max+1 per (candidate_profile_id, job_opportunity_id) under the existing lock instead of hardcoding 1
- [x] 4.2 Verify `ListResumesAction` orders by `version_no` desc (fix if needed); confirm `ResumeResource.version_no` exposure and migration reversibility via `php artisan migrate:status` / rollback dry-check reasoning documented in the change
- [x] 4.3 Tests (`tests/Feature/Resumes/*` + unit): first version = 1; version increments across draft/approved history; approved previous version does not block new draft; multiple versions list newest-first; existing rows survive (factory-seeded pre-versioning row keeps identity)

## 5. Resume staleness and approval enforcement

- [x] 5.1 Extract resume-scoped staleness comparing `profile_snapshot.fingerprint`/`opportunity_snapshot.fingerprint` against fresh fingerprint computations (reuse `FingerprintService`; do not duplicate rules)
- [x] 5.2 Use resume-scoped staleness in `ApproveResumeAction` keeping exact 409 `resume_stale` message; keep match-analysis staleness untouched for match surfaces
- [x] 5.3 Refresh snapshot fingerprints in `TailorResumeAction` and `UpdateResumeContentAction` so regenerated drafts report `stale = false`
- [x] 5.4 Tests: stale profile blocks approval (409 `resume_stale`), stale opportunity blocks approval, regenerated draft approvable, approved resume immutable (`resume_immutable`), staleness flags surface correctly in resources after profile/opportunity change

## 6. Deterministic tailoring relevance audit

- [x] 6.1 Audit `TailoringAnalyzer` two-path flow; harden minimum content floor so sparse relevance never yields empty sections when trusted content exists
- [x] 6.2 Unit tests: explicit `tailoring_relevance` path preferred; fallback deterministic (same input â†’ same scores/order); no invented facts or source ids (all references resolve to real profile items/candidate skills/profile); floor preserves summary + top items; traceability metadata intact in produced content

## 7. Clarification completeness audit + decision record

- [x] 7.1 Audit `BuildProposalAction`/question templates: enumerate which target types actually generate proposals today; record findings in this change folder (audit note)
- [x] 7.2 Confirm ProfileItem/experience/education/language/basic-field apply support is absent structurally â†’ write deferral note (limitation, future requirements per CLAR-012) into design docs; update artifacts if the audit contradicts the assumption
- [x] 7.3 Test: accepted proposal with unsupported target type returns 422 `proposal_not_supported`, mutates nothing, and records apply-failure audit event

## 8. Clarification audit events

- [x] 8.1 Migration: add nullable `event` string column to `clarification_audit_events` (with rollback dropping it)
- [x] 8.2 Introduce shared best-effort audit writer service; emit events from question creation, answer submission, proposal generation, acceptance (existing listener migrated), rejection, skip, and apply-failure paths; metadata structured only (no provider payloads/secrets)
- [x] 8.3 Tests: lifecycle events exist for created/answered/generated/accepted/rejected/skipped; apply failure audited; audit-writer exception never rolls back committed mutation (warning logged)

## 9. Dashboard baseline (frontend)

- [x] 9.1 Delete dead `frontend/src/features/dashboard/pages/DashboardPage.vue` (verify zero references)
- [x] 9.2 Harden `HomePage.vue` panels with per-query loading/empty/error/retry using Vue Query state and existing UI components; one failed panel must not blank others
- [x] 9.3 Vitest: dashboard renders error+retry on failed query, recovers on refetch, other panels unaffected; empty states asserted; no applications/tasks/interviews widgets present

## 10. X-Request-ID consistency (frontend)

- [x] 10.1 Move request-ID generation into `src/api/client/request-id.ts` and attach via Axios request interceptor; remove duplicated `nextRequestId()` from matching/clarification/cv-tailoring API modules
- [x] 10.2 Unit test: any client request carries `X-Request-ID`; grep check confirms single implementation remains

## 11. Architecture decisions documentation

- [x] 11.1 Update `docs/database/README.md`, `MCD.md`, `MLD.md`: deprecate generic FILE model (Option B chosen), document dangling `resumes.file_id` removal plan for the Application Documents phase, ownership/provenance rationale
- [x] 11.2 Record Phase D interview model recommendation (Option C: task/reminder + dedicated interview record) in `docs/database/README.md` (or ADR section) with rationale vs Options A/B

## 12. Quality gates and verification

- [x] 12.1 Backend: `php artisan test --compact` green
- [x] 12.2 Backend: `vendor/bin/pint --test` green (run `vendor/bin/pint --dirty --format agent` first if fixes needed)
- [x] 12.3 Backend: `vendor/bin/phpstan analyse` green (or existing configured level)
- [x] 12.4 Run `php artisan route:list` and `php artisan migrate:status`; confirm route table unchanged except middleware registration and migrations clean
- [x] 12.5 Frontend: `npm run type-check`, `npm run lint`, `npm run test:unit -- --run`, `npm run build` all green
- [x] 12.6 Repository: `git diff --check` clean; no unrelated dependency upgrades in lockfiles
