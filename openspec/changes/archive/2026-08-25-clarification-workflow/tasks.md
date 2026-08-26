## 1. Baseline Inspection

- [x] 1.1 Inspect existing `Matching` domain actions, `FingerprintService`, `StalenessService`, and the `match_analyses`/`match_findings` schema; confirm eligibility source fields.
- [x] 1.2 Inspect `Skills`/`Profile` domain actions and the `candidate_skills` schema (state, evidence JSON) to define the trusted-mutation entry points.
- [x] 1.3 Inspect `Opportunities` `GeneratePreviewAction`/`ConfirmOpportunityAction` and the existing idempotency/operation abstractions to reuse for proposal review.
- [x] 1.4 Run the baseline backend and frontend quality gates (Pest, Pint, PHPStan, ESLint, vue-tsc, Vitest, build) and record the starting state.

## 2. Database

- [x] 2.1 Create the `clarification_questions` migration (match_analysis_id FK, match_finding_id FK, question_no, question_type, prompt, detail, template_key, options_json, unit, status, ai_metadata; index FKs and `(match_analysis_id, status)`).
- [x] 2.2 Create the `clarification_answers` migration (user_id FK, question_id FK unique, answer_type, value, acknowledged_no_evidence, status, proposal_id FK nullable; index ownership FKs).
- [x] 2.3 Create the `clarification_proposals` migration (answer_id FK, target entity type/id, field, before, after, origin answer, status; immutable-on-accept rule).
- [x] 2.4 Add Eloquent models, model relationships, and factories for the three tables with states.
- [x] 2.5 Add MySQL constraint tests (unique answer per question, FK ownership, status enum strings) and verify migration rollback.

## 3. Backend Domain

- [x] 3.1 Add enums: `ClarificationQuestionType`, `ClarificationQuestionStatus`, `ClarificationAnswerType`, `ClarificationAnswerStatus`, `ClarificationProposalStatus`, and entity/field constants.
- [x] 3.2 Implement the deterministic question-template registry keyed by finding type and requirement importance, with stable `template_key`s.
- [x] 3.3 Implement `BuildQuestionSessionAction`: eligibility filter (partial/gap with factor 0.00/0.50, required or preferred, no trusted answer, no duplicate), impact ordering, and cap of 3 per pass.
- [x] 3.4 Implement the `ClarificationAssistant` (Laravel AI SDK, schema-validated ranking/reword only, deterministic fallback with recorded reason, minimal delimited context, no score influence).
- [x] 3.5 Implement `CreateAnswerAction`: create pending answer with idempotency (unique question_id, `answer_already_exists` on conflict).
- [x] 3.6 Implement `SkipQuestionAction`: mark question skipped, no answer/proposal created.
- [x] 3.7 Implement `BuildProposalAction`: derive the concrete proposal (target, field, before, after) from the answer and its acknowledged_no_evidence state.
- [x] 3.8 Implement `ApplyProposalAction`: transactional trusted mutation through `Skills`/`Profile` actions (verified-with-evidence, claimed-with-ack, rejected), immutability of accepted proposals, and after-commit dispatch of the stale-marking job.
- [x] 3.9 Implement `ListClarificationsAction`: open-session questions with type, options, evidence basis, and progress for the owning analysis.
- [x] 3.10 Implement stale-marking job (via `FingerprintService`/`StalenessService`) with queue, retry/backoff, timeout, and request-ID propagation; never recompute score in the mutation transaction.
- [x] 3.11 Add audit event on proposal acceptance (origin answer, target, before/after) with redacted metadata.

## 4. Backend API

- [x] 4.1 Add routes: `GET /api/v1/matches/{id}/clarifications`, `POST /api/v1/clarifications/{id}/answer`, `POST /api/v1/clarifications/{id}/review`, `POST /api/v1/clarifications/{id}/skip` under auth + CSRF + rate limiting.
- [x] 4.2 Add Form Requests with allowlist validation for answer, review, and skip payloads (evidence URL validation, acknowledged_no_evidence, value ranges).
- [x] 4.3 Add `ClarificationQuestionPolicy`, `ClarificationAnswerPolicy`, and `ClarificationProposalPolicy` with ownership scoping on every lookup (no route-binding-only authz).
- [x] 4.4 Add thin controllers (`ClarificationController`) returning API Resources; never return models directly.
- [x] 4.5 Map domain exceptions to stable RFC 9457 problem codes (e.g. `clarification_question_not_found`, `clarification_session_expired`, `answer_already_exists`, `proposal_not_reviewable`, `invalid_evidence_url`).
- [x] 4.6 Add API Resources for questions, answers, and proposals with provenance and status fields; prevent N+1 queries.
- [x] 4.7 Update OpenAPI 3.1 with the four clarification endpoints and problem codes.
- [x] 4.8 Add the generate route `POST /api/v1/matches/{id}/clarifications` with ownership scoping, a dedicated write rate limiter, and a thin controller method that builds the session transactionally (analysis-row lock + existing per-finding dedup) and returns the session resource.
- [x] 4.9 Fix the answer validation contract so `no`, `no_with_ack`, and `yes` with a no-evidence acknowledgement accept an empty/omitted `value` (`nullable`), keeping `url` required for `yes` without acknowledgement; update OpenAPI accordingly.

## 5. Backend Tests

- [x] 5.1 Feature tests: session generation (eligible/verified/low-impact/rejected findings, cap 3, ordering, empty session).
- [x] 5.2 Feature tests: answer creation (pending, duplicate conflict, skip, expired rejected, no mutation while pending).
- [x] 5.3 Feature tests: proposal review (accept verifies with evidence, no-evidence ack → claimed not verified, reject/no mutation, edit text/number, skip).
- [x] 5.4 Feature tests: staleness after accepted proposal (stale flag, old score readable, score never recomputed in mutation transaction).
- [x] 5.5 Security tests: cross-user 404 for questions/answers/proposals, unauthenticated 401, rate limit 429, CSRF enforcement.
- [x] 5.6 AI tests: fake assistant by default, malformed-assistant-output and provider-failure fallback to deterministic set, no partial question set persisted, no fabricated questions.
- [x] 5.7 Integration tests: MySQL unique constraint on answer per question, database-queue stale-marking job, migration rollback/forward-fix.
- [x] 5.8 Architecture tests: no other code path can promote a skill outside accepted proposals; domain boundaries respected.
- [x] 5.9 Feature tests: generate endpoint (creates questions for eligible findings, regenerate never duplicates, empty session, cross-user 404, unauthenticated 401, rate limit 429) and empty-value answer validation (no, no_with_ack, yes with no-evidence acknowledgement → 201).

## 6. Frontend

- [x] 6.1 Create `frontend/src/features/clarification/` (api module, types, TanStack Vue Query hooks following existing factory conventions).
- [x] 6.2 Add the clarification entry card to `MatchBriefPage.vue` (shown when open questions exist, hidden when empty) with route/state wiring.
- [x] 6.3 Implement the single-question step component (question-type-aware inputs, progress, skip/back, evidence disclosure, accessible validation).
- [x] 6.4 Implement the proposal review step (before/after summary, evidence or no-evidence acknowledgement, accept/edit/skip; verified state not editable).
- [x] 6.5 Implement state handling: loading, empty, error-with-retry (no infinite loops), expired-session with fresh-session offer, success confirmation, and query invalidation (matching + skills) on accept.
- [x] 6.6 Apply the design system (tokens, UI kit, thin borders, single indigo accent) and ensure responsive layouts at ~390, ~768, 1280, and 1440 px.
- [x] 6.7 Ensure accessibility: semantic HTML, keyboard operation, visible focus, labels, contrast (WCAG 2.2 AA), screen-reader announcements, `prefers-reduced-motion`, no `v-html`.
- [x] 6.8 Wire the generate action in the frontend session flow: generate API function, generate mutation on the session composable, a primary "Generate questions" action in the empty state, and "Start a fresh session" in the expired state triggering generate-then-refetch.

## 7. Frontend Tests and Verification

- [x] 7.1 Vitest: entry card visibility/empty states, one-question-at-a-time flow, question-type inputs, review accept/edit/skip, and all state handling.
- [x] 7.2 Vitest: accessibility assertions (labels, focus, announcements) and no-`v-html` check.
- [x] 7.3 Run `npm run format`, `npm run lint`, `npm run test:unit -- --run`, `npm run build`, and the configured type-check.
- [x] 7.4 Start the dev server and verify the flow in-browser (mobile/tablet/desktop), checking console/network and keyboard/contrast.

## 8. Documentation and Integration

- [x] 8.1 Update `docs/database/MLD.md`, `MCD.md`, and `IMPLEMENTATION_PLAN.md` to add the clarification tables and remove any remaining "future clarification-workflow" deferral notes.
- [x] 8.2 Add or update an ADR capturing the clarification trust boundary, deterministic-template-first generation, and additive API decisions.
- [x] 8.3 Update README/env example if they reference clarification endpoints or the change.

## 9. Quality Gates and Finalization

- [x] 9.1 Run backend gates: `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, and the configured PHPStan/Larastan command.
- [x] 9.2 Run frontend gates: format, lint, type-check, unit tests, and production build.
- [x] 9.3 Verify `php artisan migrate:status` shows applied clarification migrations and queue behavior for the stale-marking job.
- [x] 9.4 Run `/opsx:verify` for the change and resolve all critical findings.
- [x] 9.5 Record completion evidence: files changed, migrations, commands executed, tests passed, remaining risks, and manual verification.

## 10. Reviewed decisions (backend domain)

- **3.8/3.10 staleness fast-path fix:** `StalenessService::profileChanged()` short-circuits when `match_analyses.profile_updated_at` still equals `candidate_profiles.updated_at`. Skill-only mutations (including accepted proposals) never touched the profile row, so the fast-path missed them. Reviewed and approved: all six `Skills` write actions (`CreateCandidateSkillAction`, `UpdateCandidateSkillAction`, `DeleteCandidateSkillAction`, `AddEvidenceAction`, `UpdateEvidenceAction`, `RemoveEvidenceAction`) now call `$profile->touch()` after mutating, so the existing brief detects skill-only changes.
- **3.10 observability-only job:** `ObserveClarificationStalenessJob` only loads the analysis/profile/opportunity and logs `StalenessService::differingVersions()` results; it never recomputes the score and never persists. Queue from `config('clarification.queue', 'clarification')` (`CLARIFICATION_QUEUE` env); `clarification.staleness_job` config carries `timeout`, `tries`, `backoff`; job uses `WithoutOverlapping((string) $analysisId)` and injects `RequestIdContext`.
- **3.11 audit wiring:** `WriteClarificationAuditEventListener` is registered in `AppServiceProvider::boot()` via `Event::listen(ProposalAccepted::class, ...)`. It writes `clarification_audit_events` best-effort (catches `Throwable`, logs a warning with the request ID) and never throws into the already-committed mutation.
- **Tests:** `ApplyProposalActionTest` (11 feature tests) covers verified/claimed paths, idempotency, conflicts, gap joins, and after-commit job dispatch + audit row.
- **4.8 generate endpoint (sync):** `POST /api/v1/matches/{id}/clarifications` builds the session synchronously and returns the session resource. The assistant is opt-in (`clarification.assistant.enabled`, default false) and the codebase has no operations/polling infra (CV ingestion is sync + status polling; `matches.recalculate` is sync), so a 202/operation flow is not warranted yet.
- **4.9 answer validation fix:** the answer contract now treats `value` as nullable for `no`, `no_with_ack`, and `yes` with `acknowledged_no_evidence=true`; `url` remains required only for `yes` without an acknowledgement. This aligns the API with CLAR-005's no-evidence path.

## 11. Reviewed decisions (production wiring, backend-driven signals)

- **Unknown findings are eligible:** `BuildQuestionSessionAction::isEligible` now accepts `MatchState::Unknown` alongside `Partial`/`Gap`. The preferred-skill exclusion covers `Gap` OR `Unknown` (a preferred finding that would otherwise be excluded must not generate a question). Category-null unknowns stay excluded because no resolvable template exists. `QuestionTemplateRegistry::findingTypeFor` maps `Unknown` to the same gap finding types per category (`SkillMissing`, `ExperienceMissing`, `LanguageMissing`, `EvidenceMissing`); `null` remains for unclassified. Tests cover required-unknown → question, preferred-unknown excluded, category-null excluded, and the `eligibleCount` 0→1→0→1 lifecycle.
- **Single planning source:** `BuildQuestionSessionAction` now has one private `plannedFor(MatchAnalysis)` that owns eligibility + template resolution + impact ordering + `take(max_questions)`. Both `execute()` and the new public `eligibleCount(MatchAnalysis): int` use it. Because already-planned findings are no longer eligible, the count drops to 0 after a generate pass.
- **`generable_count` session signal (no new endpoint):** embedded in `ClarificationSessionData` and `ClarificationSessionResource`; `ListClarificationsAction` computes it via `BuildQuestionSessionAction::eligibleCount`. OpenAPI `ClarificationSession` schema updated (required field). CTA rule: `generable_count > 0` with no actionable questions → "Generate questions"; actionable questions → "Answer/Continue review"; both zero → CTA hidden; never show a CTA leading to an empty/dead-end flow. Session semantics: `questions[]` = actionable only (pending OR answered-with-pending-review); `progress` counts all questions; CTA logic uses the actionable list, never progress.
- **Frontend: dedicated route supersedes the inline drawer:** new lazy route `opportunities/:id/match/clarifications` (`opportunities-match-clarifications`) hosts `ClarificationPage.vue` (reuses `useMatchAnalysis` + `MatchBriefHeader`/`MatchStatusPanel`/`MatchErrorState`/`InsufficientProfileGate`/`ClarificationFlow`; back link to the match brief). `MatchBriefPage.vue` keeps only the `ClarificationEntryCard` (after `MatchAtAGlance`) which pushes to the route. The entry card implements the four backend-driven states from the design (answer / review / generate / hidden).
