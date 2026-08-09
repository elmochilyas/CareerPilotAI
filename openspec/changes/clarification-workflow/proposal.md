## Why

When a match analysis finds high-impact uncertainty — a required skill the candidate has never recorded, a claimed skill with no evidence, or a partial match on an ambiguous requirement — today the system silently scores the item at factor 0.00 or 0.50 and the Career Intelligence Brief shows a `gap` or `partial` finding. The candidate has no way to correct or confirm that uncertainty, so matches stay inaccurate even when the candidate actually possesses the missing requirement. `openspec/config.yaml` reserves `MATCH-004` ("High-impact uncertainty creates clarification questions, not silent changes") and `MATCH-005` ("Profile-changing answers require evidence or explicit no-evidence acknowledgement"), and the `deterministic-match-engine` change explicitly deferred the clarification tables and the reserved `POST /api/v1/clarifications/{id}/answer` endpoint to this `clarification-workflow` change.

This change introduces a clarification workflow: the match brief surfaces a bounded set of targeted, one-at-a-time questions for uncertain findings; the candidate answers them; a proposal with evidence or an explicit no-evidence acknowledgement is shown for review; and only after explicit candidate acceptance are trusted profile mutations applied transactionally. The MatchAnalysis then becomes stale via its fingerprint and is recalculated. This is a full-stack change built on the existing `Matching`, `Skills`, and `Opportunities` domains, the `ConfirmOpportunityAction` preview/confirm pattern, and the mandatory design system.

## What Changes

- **Clarification eligibility**: questions are generated only for findings that are high-impact and uncertain — `match_state = partial` or `gap` with a `factor` of 0.00 or 0.50 — and only when the candidate genuinely lacks a trusted, traceable answer. Already-verified skills, rejected/archived skills, and low-impact gaps are excluded. A per-analysis answer session enforces at most ~3 questions per pass so the brief stays scannable.
- **Deterministic-first question generation**: the engine composes candidate questions from approved, versioned templates driven by the finding type and source evidence — never by free-form LLM prose. The LLM is used only inside a narrow, validated `ClarificationAssistant` to rank and reword already-eligible questions (schema-validated, no new facts), never to decide eligibility or to invent questions.
- **One-at-a-time answer flow**: the UI shows exactly one question at a time (per `INT-003`/`MATCH-004` conventions), with a progress indicator and skip, matching `clarification_questions` to a single answer per question (config invariant "One answer per clarification question").
- **Answer lifecycle and trust boundary**: answers are stored as candidate input with a status (`pending`, `accepted`, `rejected`, `skipped`, `expired`); the boundary between "candidate input" and "trusted profile data" is explicit. Accepted, evidence-bearing answers produce trusted mutations; explicit no-evidence acknowledgements produce a downgraded/acknowledged state, never silent verification. Missing skills remain missing unless the candidate provides evidence or an explicit acknowledgement.
- **Review-then-apply**: generation produces a proposal (target entity, field, before/after value, origin = the accepted answer) that the candidate reviews, edits where the domain allows, accepts, or skips. Apply is transactional and reuses the `ConfirmOpportunityAction` version-token/preview/confirm pattern; accepted proposal state becomes immutable and is linked to the answer, finding, and analysis for audit.
- **Staleness instead of direct mutation**: profile-changing answers mark the owning `MatchAnalysis` stale through the existing fingerprint service and trigger recalculation; the match engine never recomputes in the same transaction as the profile write.
- **API**: minimal REST additions under `/api/v1` — `GET /api/v1/matches/{id}/clarifications` (list/session), `POST /api/v1/clarifications/{id}/answer`, `POST /api/v1/clarifications/{id}/answer/{id}/review` — with ownership policies (cross-user 404), RFC 9457 problem details, and rate limiting, using the reserved `/api/v1/clarifications/{id}/answer` shape from `config.yaml`.
- **Frontend**: a clarification flow integrated into the match brief (`MatchBriefPage.vue`) — question card, single-question step, proposal review panel, success/empty/error/loading/expired states — plus the async operation polling pattern already used by match creation.

### Contradictions resolved

- `config.yaml` reserves only `POST /api/v1/clarifications/{id}/answer`. This change needs a question list endpoint and a review step to satisfy the mandatory review-then-apply and explainability rules, so it adds `GET /api/v1/matches/{id}/clarifications` and `POST /api/v1/clarifications/{id}/review` alongside the reserved endpoint. These are additive and do not change any already-approved route.
- The archive notes that clarification tables "belong to the later clarification-workflow change"; `docs/database/MLD.md` defers them similarly. This change is that later change: the two planned tables (`clarification_questions`, `clarification_answers`) from `config.yaml` become real migrations.
- Candidate skills already carry `evidence` JSON and `state`; no new profile version column is added — change detection stays in `Matching`'s `FingerprintService` as designed.

## Capabilities

### New Capabilities

- `clarification-workflow`: Backend workflow — question session generation, one-at-a-time answers, proposal review/accept/edit/skip, transactional trusted mutation, staleness marking, audit links, and the clarification API.
- `clarification-ui`: Frontend clarification flow in the match brief — single-question steps, proposal review, all state coverage, responsive and accessible per the design system.

### Modified Capabilities

- `match-api` (delta): add clarification routes and problem codes; keep existing match endpoints unchanged.
- `match-engine` (delta): expose uncertain findings as clarification candidates with deterministic question templates; document that accepted answers drive staleness + recalculation, never silent scoring changes.
- `match-brief-ui` (delta): embed the clarification entry point, question step, and review panel into the existing brief page and its states.
- `skill-evidence` (delta): document the evidence-to-answer link and the no-evidence acknowledgement state that accepted clarification answers produce.

## Impact

### Backend

- **New files** in `backend/app/Domain/Clarification/` (`Actions/`, `Data/`, `Enums/`, `Policies/`, `Services/`), plus `Http/Controllers/Api/V1/`, `Requests/`, `Resources/` and matching `Support/ProblemDetails` entries.
- **Reused**: `Matching`'s `FingerprintService`/`StalenessService`, the `ConfirmOpportunityAction` version-token pattern, existing idempotency and operation abstractions, `app/Support/ProblemDetails`.
- **No new dependencies.**

### Frontend

- **Modified page**: `frontend/src/features/matching/pages/MatchBriefPage.vue` gains the clarification entry point and flow.
- **New files**: `frontend/src/features/clarification/` — API module, TanStack Vue Query hooks, question step + review panel components, types.
- **Reused**: TanStack Vue Query factory conventions, UI kit (`Button`, `Card`, `Progress`, `Skeleton`), design tokens, `extractProblemDetail`. No new dependencies.

### Database

- **New tables**: `clarification_questions`, `clarification_answers` (plus proposal/acknowledgement columns and indexes as designed). Migrations include rollback and MySQL constraint tests.

### Security and privacy

- Every lookup is ownership-scoped with policies (cross-user 404); route model binding is not used as an authorization check. No credentials or PII reach the client beyond the candidate's own data. The `ClarificationAssistant` receives only the minimal delimited context needed (finding + candidate-owned fields), never secrets or session data. No `v-html`.

### Risks and assumptions

- **Trust boundary**: the single most important design constraint — accepted answers are the only source of profile mutations; the LLM cannot mutate, cannot create questions, and cannot change the score.
- **Deterministic fallback**: if the AI assistant is unavailable or its schema-validated output fails validation, the deterministic question set is used as-is; provider failure yields a safe status with no partial update.
- **Existing route**: the match-brief entry point already exists (`opportunities-match`); only the clarification flow is added inside it.

### Out of scope

- Changing match weights, factors, or the deterministic formula
- Silent or automatic profile mutation from any AI output
- Chat-style clarification UI
- Verification of claims by third parties (link verification is future work in skill-evidence)
- Applications, learning roadmap, and interview flows
- New dependencies or design-token changes

### Acceptance criteria

- AC-CLAR-01: Only high-impact uncertain findings (`partial`/`gap` with factor 0.00/0.50 and no trusted answer) generate clarification questions; verified/rejected/archived skills and low-impact gaps never do.
- AC-CLAR-02: Question generation is deterministic-template-first; the AI assistant only ranks/rewords eligible questions with schema validation, and its failure falls back to the deterministic set without partial updates.
- AC-CLAR-03: The flow asks one question at a time with progress, skip, and edit; one answer per question is enforced at the database level.
- AC-CLAR-04: No profile mutation happens until the candidate explicitly accepts a proposal; evidence-bearing accepts produce trusted mutations and no-evidence accepts produce an explicit acknowledgement state; missing skills remain missing otherwise.
- AC-CLAR-05: Accepted profile-changing answers mark the owning analysis stale via fingerprint and trigger recalculation; the score is never recomputed during the profile write.
- AC-CLAR-06: All clarification resources enforce ownership policies with cross-user 404 and rate limiting; RFC 9457 problem details are returned for validation, conflict, and not-found cases.
- AC-CLAR-07: The brief shows loading, empty (no eligible questions), expired-session, error-with-retry, and success states; the flow passes lint, format, vue-tsc, Vitest, and the production build and is verified in-browser at ~390, ~768, 1280, and 1440 px with keyboard focus and contrast compliance.

### Requirement IDs

- CLAR-001: High-impact uncertainty surfaces targeted clarification questions instead of silent score changes.
- CLAR-002: Eligibility is limited to uncertain, high-impact findings without a trusted answer.
- CLAR-003: Question generation is deterministic-template-first with a bounded AI ranking/reword role.
- CLAR-004: At most ~3 questions per pass; one question shown at a time with skip and edit.
- CLAR-005: One answer per question, persisted as candidate input with explicit status.
- CLAR-006: Answers never mutate profile data until an explicit proposal acceptance.
- CLAR-007: Evidence-bearing accepts produce trusted mutations; no-evidence accepts require an explicit acknowledgement.
- CLAR-008: Accepted profile changes mark the match analysis stale and trigger recalculation.
- CLAR-009: Clarification API endpoints under `/api/v1` with ownership scoping and RFC 9457 errors.
- CLAR-010: Frontend clarification flow with complete state coverage and design-system conformance.
