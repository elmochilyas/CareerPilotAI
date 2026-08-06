## 1. Repository inspection and baseline

- [x] 1.1 Inspect current database schema for `candidate_profiles`, `candidate_skills`, `profile_items`, `job_opportunities`, `job_requirements`, `job_opportunity_skills` and confirm no matching tables exist
- [x] 1.2 Inspect `Matching` domain, `CvIngestion` async AI pattern (state machine enums, `CvAnalyzer` contract, `OpenAiCvAnalyzer`), and `Opportunities` policies/actions for conventions
- [x] 1.3 Inspect existing API routes, controllers, Form Requests, and API Resources under `/api/v1`
- [x] 1.4 Inspect frontend feature structure (opportunities, cv-ingestion), API client, problem-detail client, router, and UI primitives
- [x] 1.5 Run baseline backend tests (`php artisan test --compact`) and confirm green
- [x] 1.6 Run baseline frontend checks (`npm run lint`, `npm run test:unit -- --run`) and confirm green

## 2. Configuration

- [x] 2.1 Create `config/matching.php` with weights (0.500/0.200/0.150/0.100/0.050), factors (1.00/0.50/0.20/0.00), `scoring_version`, `algorithm_version`, `classifier_schema_version`, queue name, job timeout/retry/backoff, rate limits, max_requirements_per_analysis, insufficient-profile thresholds, classifier model/timeout
- [x] 2.2 Add `MATCHING_QUEUE=matching` to `.env` and `.env.example`

## 3. Database migrations

- [x] 3.1 Create migration for `match_analyses` table (FKs, `operation_key` UNIQUE per profile, indexes, CHECK constraints on score ranges, timestamps)
- [x] 3.2 Create migration for `match_scores` table (UNIQUE(analysis, category), weight, score, achieved/total points)
- [x] 3.3 Create migration for `match_findings` table (source refs, snapshot text, importance, match_state, factor, evidence_refs JSON, indexes on importance/match_state)
- [x] 3.4 Run `php artisan migrate` and verify tables, constraints, and indexes via `php artisan migrate:status` and schema inspection
- [x] 3.5 Document rollback (each migration has `down()`; forward-fix note) 

## 4. Backend domain: Enums and Data

- [x] 4.1 Create `MatchAnalysisStatus` enum (`queued`, `processing`, `completed`, `failed`) with state-machine helpers (`isProcessing`, `isTerminal`, `isRetryable`)
- [x] 4.2 Create `MatchState` enum (`matched`, `partial`, `gap`, `unknown`)
- [x] 4.3 Create `MatchCategory` enum (`required_skills`, `preferred_skills`, `evidence`, `experience_education`, `language_soft`)
- [x] 4.4 Create `RequirementSourceType` enum (`job_requirement`, `job_opportunity_skill`)
- [x] 4.5 Create immutable DTOs: `MatchRequirement`, `MatchFindingResult`, `MatchScoreComponent`, `MatchAnalysisResult`, `ClassifierResult`, `ClassifierFinding`
- [x] 4.6 Create `MatchAnalysisData` DTO for domain/controller boundary use

## 5. Backend domain: Models

- [x] 5.1 Create `MatchAnalysis` Eloquent model (casts, relationships to profile/opportunity/scores/findings)
- [x] 5.2 Create `MatchScore` Eloquent model
- [x] 5.3 Create `MatchFinding` Eloquent model
- [x] 5.4 Create factories for `MatchAnalysis`, `MatchScore`, `MatchFinding` with usable states (queued, completed, failed)

## 6. Backend domain: Fingerprints and deterministic scoring engine

- [x] 6.1 Create `ProfileSnapshot` / `OpportunitySnapshot` canonical serialization (sorted, stable field projection limited to matching-relevant values)
- [x] 6.2 Create `FingerprintService` computing SHA-256 fingerprints for profile and opportunity
- [x] 6.3 Create `RequirementCollector` service loading confirmed requirements from `job_requirements` and `job_opportunity_skills` into `MatchRequirement` DTOs with importance snapshot
- [x] 6.4 Create deterministic evaluators per requirement type (skill state → factor mapping, language mapping, experience/education via `profile_items`, soft-skill/unstructured deferral to classifier or `unknown`)
- [x] 6.5 Create `MatchScoreCalculator` implementing the approved weights/factors/formula and `evidence_coverage_score`
- [x] 6.6 Create `StalenessService` comparing stored vs current fingerprints with `updated_at` pre-check
- [x] 6.7 Unit tests for fingerprint stability, score formula, factor mapping, and `unknown`-vs-`gap` distinction

## 7. Backend domain: Semantic classifier (AI)

- [x] 7.1 Create `RequirementClassifier` interface (contract, following `CvAnalyzer` pattern)
- [x] 7.2 Create `RequirementClassifierSchemaValidator` validating enums, references, limits, and business rules
- [x] 7.3 Define classifier prompt template with delimited untrusted requirement/profile text and no-score instruction
- [x] 7.4 Create `OpenAiRequirementClassifier` using Laravel AI SDK `agent()` + `JsonSchema`, recording provider/model/prompt version/latency/tokens/status
- [x] 7.5 Ensure classifier output numeric values are ignored and never feed the final score
- [x] 7.6 Fake classifier for tests (success, malformed, invalid-schema, provider-failure variants)

## 8. Backend domain: Analysis actions and async job

- [x] 8.1 Create `CreateMatchAnalysisAction` (validation, insufficient-profile gate, fingerprinting, `queued` row, operation_key, after-commit dispatch)
- [x] 8.2 Create `ProcessMatchAnalysisJob` on the `matching` queue (status transitions, deterministic evaluation, classifier call, transactional persistence, safe failure codes)
- [x] 8.3 Create `ShowMatchAnalysisAction`, `ListMatchAnalysesAction`, `RecalculateMatchAnalysisAction`
- [x] 8.4 Implement idempotency: duplicate creation with the same operation_key returns the existing operation; no duplicate rows
- [x] 8.5 Implement request-ID propagation through job and provider calls
- [x] 8.6 Integration test: database-queue job completes, retries, and leaves safe failed state

## 9. Backend domain: Policies, API, and resources

- [x] 9.1 Create `MatchAnalysisPolicy` (ownership via candidate profile) and register authorization on every owned lookup
- [x] 9.2 Create Form Requests: `CreateMatchRequest`, `ListMatchesRequest`, `RecalculateMatchRequest`
- [x] 9.3 Create API Resources: `MatchAnalysisResource`, `MatchScoreComponentResource`, `MatchFindingResource`, `MatchOperationResource`
- [x] 9.4 Create `MatchAnalysisController` with `store` (202 + Location), `index`, `show`, `recalculate` under `/api/v1`
- [x] 9.5 Register routes: `POST /api/v1/opportunities/{id}/matches`, `GET /api/v1/opportunities/{id}/matches`, `GET /api/v1/matches/{id}`, `POST /api/v1/matches/{id}/recalculate` inside the auth+throttle group
- [x] 9.6 Add rate limiting for match creation/recalculate and map domain exceptions to stable RFC 9457 problem codes

## 10. Backend tests

- [x] 10.1 Feature tests: async creation (202, Location, poll to completed), list newest-first + latest flag, read full analysis, recalculate creates a new snapshot
- [x] 10.2 Security tests: unauthenticated 401; cross-user access returns 404 with no existence leak (BOLA); unconfirmed opportunity returns 409
- [x] 10.3 AI tests: malformed output → safe failure; invalid schema → `unknown`/failed with no partial trusted update; provider failure → safe failed state; classifier numeric score ignored
- [x] 10.4 Staleness tests: profile change marks stale; opportunity change marks stale; recalc keeps old snapshot immutable
- [x] 10.5 Determinism tests: identical inputs → identical score; required weight > preferred weight; `unknown` distinct from `gap`
- [x] 10.6 Insufficient-profile gate test: below thresholds → gate state, no numeric score
- [x] 10.7 Run `php artisan test --compact` green and `vendor/bin/pint --dirty --format agent`

## 11. Frontend: feature module, types, API, composable

- [x] 11.1 Create `frontend/src/features/matching/types/index.ts` mirroring the API resources
- [x] 11.2 Create `frontend/src/features/matching/api/index.ts` (axios calls, problem-detail mapping, X-Request-ID)
- [x] 11.3 Create `frontend/src/features/matching/composables/useMatchAnalysis.ts` (Vue Query queries, polling, create/recalculate mutations, invalidation)

## 12. Frontend: components and pages

- [x] 12.1 Create `MatchBriefPage.vue` page assembling context, score anchor, meters, findings workspace, and status panels
- [x] 12.2 Create `ScoreAnchor.vue` (single anchor at 48–64 px) and `CategoryMeter.vue` (horizontal meters) using design tokens
- [x] 12.3 Create `RequirementResultRow.vue` (requirement, importance, state, evidence refs, suggestion) and `RequirementFilterBar.vue` (All/Matched/Partial/Gaps/Unknown × Required/Preferred)
- [x] 12.4 Create `MatchStatusPanel.vue` (queued/processing with `aria-live` polling), `StaleNotice.vue` (with recalculate), `InsufficientProfileGate.vue`, `MatchErrorState.vue` (retry, no infinite loops)
- [x] 12.5 Register lazy route `/opportunities/:id/match` inside `DefaultLayout`
- [x] 12.6 Implement empty workspace, error/retry, 401/419 handling, keyboard/focus/contrast, `prefers-reduced-motion`, no `v-html`; verify 1280–1440 px and ~390 px layouts

## 13. Frontend tests and quality

- [x] 13.1 Vitest component tests: filter composition, state rendering, stale notice, gate, error/retry
- [x] 13.2 Vitest composable tests with mocked API: create/poll/recalculate flows
- [x] 13.3 Run `npm run format`, `npm run lint`, `npm run test:unit -- --run`, vue-tsc type-check, `npm run build`

## 14. Documentation

- [x] 14.1 Update `docs/database/MLD.md`, `MCD.md`, `IMPLEMENTATION_PLAN.md` to replace the merged `opportunity_analyses` design with `match_analyses`, `match_scores`, `match_findings` (and note clarification tables belong to the later clarification-workflow change)
- [x] 14.2 Update OpenAPI 3.1 with the four match endpoints and resources; keep generated types in sync if a generator is configured
- [x] 14.3 Update README/ADR/runbook notes affected by matching (queue name, recalc semantics) if applicable

## 15. Verification

- [x] 15.1 Run `openspec status --change "profile-job-matching" --json` and confirm all artifacts present
- [x] 15.2 Run `/opsx:verify` and resolve all critical findings
- [x] 15.3 Run backend gates (Pint, PHPStan/Larastan, Pest) and frontend gates (lint, format, test:unit, vue-tsc, build)
- [x] 15.4 Update any task/spec/design drift discovered during apply; confirm `applyRequires` tasks all complete
