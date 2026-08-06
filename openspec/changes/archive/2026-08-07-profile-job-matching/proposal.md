## Why

Candidates can now build a trusted profile and save confirmed job opportunities, but the product gives them no explainable fit signal. Matching is the ninth change in the approved delivery order (`deterministic-match-engine`) and the first moment the product turns trusted profile and trusted job data into career value: a truthful, reproducible, evidence-based analysis of "how well does my profile match this opportunity". It unlocks resume tailoring, application tracking, and the learning roadmap that depend on match output. Without it, the candidate cannot decide where to invest application effort.

This change must also reconcile a material data-model conflict: the project-wide source of truth (`openspec/config.yaml`) and the implemented schema normalize requirements into separate rows, while `docs/database/MLD.md`, `MCD.md`, and `IMPLEMENTATION_PLAN.md` still describe a single merged `opportunity_analyses` table with JSON columns. Per the authority policy, the normalized direction wins and the docs are updated in this change.

## What Changes

**New capability — Profile-to-job match analysis**, owned by the previously empty `Matching` domain.

- **Deterministic match engine (Laravel)**: computes the overall match score and per-requirement match states from trusted candidate profile data and confirmed opportunity requirements. The final numeric score is produced by deterministic Laravel code using the approved weights (required 50%, preferred 20%, evidence 15%, experience/education 10%, languages/soft skills 5%) and factors (verified 1.00, claimed/needs-clarification 0.50 shown separately, learning 0.20, missing/rejected 0.00). The LLM never decides the score.
- **Requirement-level results**: every confirmed requirement (skill, education, experience, language, soft skill, unstructured) gets an independent result with a match state — `matched`, `partial`, `gap`, `unknown` — where `unknown` is distinct from `gap` and means the profile has no trustworthy evidence to decide. Required vs preferred importance is never flattened.
- **Semantic AI comparisons, bounded**: AI is used only for responsibility relevance, transferable experience, education equivalence, and classification of unstructured requirements. Output is structured, schema-validated, provider-versioned, and never modifies the profile or the opportunity and never decides the final score.
- **Explainability**: separate Overall Match score and Evidence Coverage; category scores exposed; each requirement result carries non-duplicative evidence references (candidate skill/evidence ids, profile item ids, source snippets) so the claim "X matched because Y" is always traceable. Critical missing requirements surface as warnings independently of the score.
- **Reproducibility and staleness**: each analysis stores fingerprints of the profile, opportunity, algorithm, and scoring versions. When the profile or opportunity changes after an analysis, the old result stays visible with a "Profile/opportunity changed since this analysis" notice and a Recalculate action; recomputation creates a new snapshot linked to exact source versions.
- **Async by default**: analysis creation returns `202 Accepted` with an operation resource and polling endpoint. The queue is database-backed (`matching` queue), idempotent, with safe retry and safe failure states; a failed analysis leaves no partial trusted update.
- **API**: minimal REST additions under `/api/v1` — `POST /api/v1/opportunities/{id}/matches`, `GET /api/v1/opportunities/{id}/matches`, `GET /api/v1/matches/{id}`, `POST /api/v1/matches/{id}/recalculate` — with ownership policies (cross-user 404), RFC 9457 problem details, and rate limiting. Clarification endpoints (`POST /api/v1/clarifications/{id}/answer`) are explicitly out of scope (separate `clarification-workflow` change).
- **Career Intelligence Brief UI**: one screen per opportunity showing opportunity context, a single score anchor, horizontal category meters, a strong-matches/gaps/unknowns summary, a requirement evidence workspace with compact filters (All / Matched / Partial / Gaps / Unknown and Required / Preferred), processing and stale states, an insufficient-profile gate, and intentional error states, built to `docs/design/CAREERPILOT_PREMIUM_DESIGN_SYSTEM.md` and WCAG 2.2 AA.

### Contradictions resolved

`openspec/config.yaml` (highest authority, "Planned tables") lists normalized matching tables — `match_analyses`, `match_scores`, `match_findings`, `clarification_questions`, `clarification_answers` — and the implemented schema already normalizes requirements (`job_requirements`, `job_opportunity_skills`). `docs/database/MLD.md`, `MCD.md`, and `IMPLEMENTATION_PLAN.md` instead describe one merged `opportunity_analyses` table (1:1 per opportunity) with requirements, findings, and clarification stored as JSON columns and no separate requirement tables. This change adopts the normalized direction: `job_match_analyses` (analysis header, fingerprints, versions, overall/category results), `job_match_requirement_results` (one row per requirement result), and `job_match_scores` (per-category score components). `MLD.md`, `MCD.md`, and `IMPLEMENTATION_PLAN.md` are updated in the docs task to remove the merged-JSON design.

## Capabilities

### New Capabilities

- `match-engine`: Deterministic match computation, per-requirement match states (`matched`/`partial`/`gap`/`unknown`), category scores, overall score, evidence coverage, fingerprints, versioned snapshots, staleness detection, and analysis lifecycle (queued → processing → completed / failed) with idempotency and safe failure.
- `match-semantic-classifier`: Bounded, structured, validated AI comparisons for responsibility relevance, transferable experience, education equivalence, and unstructured-requirement classification. Never produces the final score and never mutates profile or opportunity data.
- `match-api`: Candidate-facing REST endpoints with ownership authorization (cross-user 404), RFC 9457 problem details, rate limiting, async `202` creation, and polling; exposes category scores, matches, uncertain items, gaps, and suggestions per MATCH-002.
- `match-brief-ui`: The Career Intelligence Brief screen — opportunity context, single score anchor, category meters, requirement evidence workspace with filters, processing/stale/insufficient-profile/error states, and accessibility — within a new `frontend/src/features/matching/` module.

### Modified Capabilities

- None. No existing canonical spec changes behavior; this change adds new capabilities only.

## Impact

### Backend

- **Domain**: `backend/app/Domain/Matching/` becomes active with `Actions`, `Data` (immutable DTOs), `Enums` (match states, analysis status, categories), `Policies` (ownership), `Services` (deterministic scorer, fingerprint builder, semantic classifier contract + OpenAI implementation, schema validator).
- **Controllers**: `MatchAnalysisController` under `Http/Controllers/Api/V1/`; new Form Requests and API Resources (`MatchAnalysisResource`, `MatchRequirementResultResource`, `MatchScoreComponentResource`).
- **Routes**: additions under `routes/api.php` inside the existing `auth:sanctum` + throttle group.
- **Queues**: `matching` queue; analysis job(s) with retry/backoff/timeout, after-commit dispatch, stable operation keys, and request-ID propagation.
- **Config**: `config/matching.php` (weights, factors, schema version, rate limits, job policy). No new dependencies.
- **Tests**: unit tests for the deterministic scorer and fingerprinting; feature tests for async creation, ownership/BOLA, malformed AI output, safe failure; integration tests for the database queue.

### Frontend

- **New feature**: `frontend/src/features/matching/` with `api/`, `types/`, `composables/` (`useMatchAnalysis`), `components/`, and `pages/`.
- **New routes**: brief page under an existing opportunity detail route (e.g. `/opportunities/:id/match`), lazy-loaded inside `DefaultLayout`.
- **New components**: score anchor, category meter, requirement result row, evidence reference, filter bar, processing/status panel, stale notice, insufficient-profile gate, error state. No new dependencies; reuse existing `components/ui/` primitives and the RFC 9457 problem-detail client.

### Database

- **New migrations**: `job_match_analyses`, `job_match_requirement_results`, `job_match_scores` with ownership FKs, indexes, unique constraints, and versioning fields (see design.md).
- **Existing tables**: read-only use of `candidate_profiles`, `candidate_skills`, `skill_evidence`, `profile_items`, `job_opportunities`, `job_requirements`, `job_opportunity_skills`. No columns changed.
- **Docs**: `docs/database/MLD.md`, `MCD.md`, and `IMPLEMENTATION_PLAN.md` updated to remove the merged `opportunity_analyses` design and reflect the normalized matching tables.

### Security and privacy

- **Ownership**: every match resource is candidate-owned; policies authorize every lookup; cross-user access returns 404 (BOLA).
- **Prompt injection**: untrusted job/requirement text is delimited in prompts and treated as data, never instructions.
- **Data minimization**: only required profile/job fields reach the semantic classifier; no unrelated personal data; no raw analysis payloads in logs.
- **Rate limits**: analysis creation and recalculate throttled per user; async acceptance within the configured AI burst limits.
- **Safe failure**: provider or validation failure produces a safe status (`failed`) with a stable problem code and no partial trusted update.

### Risks and assumptions

- **AI semantic quality**: bounded by strict structured output, validation, and evaluation gates; deterministic score remains fully testable without a provider.
- **Queue worker**: requires active `php artisan queue:work`; assumed part of local setup.
- **Staleness semantics**: recomputation creates new snapshots; old analyses remain readable — assumed acceptable for the MVP and required by MATCH-006.
- **Insufficient profile**: an incomplete profile produces a gate state rather than a misleading low score.

### Out of scope

- Clarification questions and answers (`clarification-workflow` change)
- CV tailoring, cover letters, resume generation
- Profile rewriting, enrichment, or auto-adding skills
- Company research and interview preparation
- Application tracking and status transitions
- Job ranking, recommendations, or multi-opportunity comparison
- Learning roadmap recalculation
- Notifications and quotas enforcement (later platform change)
- Job-board scraping, URL fetching, or browser automation

### Acceptance criteria

- AC-MATCH-01: A candidate with a trusted profile creates a match for a confirmed opportunity. The system returns `202`, then a completed analysis with an overall score computed by deterministic Laravel code.
- AC-MATCH-02: For the reference candidate (PHP, Laravel, MySQL, REST APIs, Sanctum, Git, queues) against the demo job (also Docker, Pest, Redis), Laravel/MySQL/REST/queues show `matched`, Pest shows `partial` (or `unknown`), Docker/Redis show `gap`, and critical missing requirements appear as warnings independent of the score.
- AC-MATCH-03: The analysis exposes per-category scores, per-requirement states, evidence references, and the scoring version without exposing any other candidate's data.
- AC-MATCH-04: Required vs preferred importance is preserved in the UI and in the stored results; the required-skills weight (50%) is greater than the preferred-skills weight (20%).
- AC-MATCH-05: Editing the profile or the opportunity after an analysis produces a stale notice on the old result and a Recalculate action; recalculating creates a new snapshot with new fingerprints.
- AC-MATCH-06: A second candidate cannot access the first candidate's analysis by guessed ID; the system returns 404.
- AC-MATCH-07: Provider failure during a semantic comparison leaves the analysis in a safe failed state with a stable problem code and no partial score.
- AC-MATCH-08: The Career Intelligence Brief renders at desktop (1280–1440 px) and mobile (~390 px) with all async, empty, error, retry, and accessibility states per the design system and WCAG 2.2 AA.

### Requirement IDs

- MATCH-001: Final numeric score is deterministic Laravel code, never direct LLM output.
- MATCH-002: Expose category scores, matches, uncertain items, gaps, and suggestions.
- MATCH-003: AI classification/normalization must pass schema and business validation.
- MATCH-006: Every recomputation creates a snapshot linked to exact source versions.
- (MATCH-004/005 — clarification workflow — intentionally out of scope for this change.)
