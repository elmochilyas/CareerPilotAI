## Context

CareerPilot currently delivers authentication, a candidate profile with typed items and a completion score, a skill catalog with states (claimed/verified/learning/rejected/archived) and evidence stored as JSON on `candidate_skills`, CV ingestion, and job-opportunity ingestion that produces confirmed opportunities with normalized `job_requirements` and `job_opportunity_skills` rows. The `Matching` domain directory exists but is empty. `openspec/config.yaml` reserves `MATCH-001..006`, the default weights/factors/formula, the endpoints `POST /api/v1/opportunities/{id}/matches` and `POST /api/v1/clarifications/{id}/answer`, and planned matching tables (`match_analyses`, `match_scores`, `match_findings`, `clarification_questions`, `clarification_answers`).

Relevant existing files:
- `openspec/config.yaml` — MATCH-001..006, weights, factors, formula, async states, API contract, planned tables, rate limits
- `docs/database/MLD.md`, `MCD.md`, `IMPLEMENTATION_PLAN.md` — contain a conflicting merged `opportunity_analyses` JSON design (resolved in this change)
- `docs/design/CAREERPILOT_PREMIUM_DESIGN_SYSTEM.md` — mandatory UI standard
- `backend/app/Domain/CvIngestion/` — async AI pipeline pattern (state machine enums, `Contracts/CvAnalyzer`, `OpenAiCvAnalyzer` with Laravel AI SDK `agent()` + `JsonSchema`, suggestion review, policies)
- `backend/app/Domain/Opportunities/` — confirmed-opportunity actions, policies (`JobOpportunityPolicy`), resources
- `backend/app/Domain/Skills/Enums/SkillState.php`, `ProficiencyLevel.php` — trusted skill states and proficiency
- `backend/app/Models/CandidateSkill.php` — `state`, `proficiency_level`, `evidence` (JSON array), `skill_id`/`custom_skill_name`
- `backend/app/Models/JobRequirement.php`, `JobOpportunitySkill.php` — requirement sources with `category`, `content`, `classification` (required/preferred), `language`, `source_evidence`
- `backend/routes/api.php` — `/api/v1` + `auth:sanctum` + `throttle:120,1`
- `backend/config/cv-ingestion.php`, `config/job-ingestion.php` — config-file conventions
- `frontend/src/features/opportunities/`, `frontend/src/features/cv-ingestion/` — feature module conventions (api/types/composables/components/pages)
- `frontend/src/api/client/problem-detail.ts` — RFC 9457 client mapping
- `frontend/src/router/index.ts` — lazy route registration inside `DefaultLayout`

Constraint notes: there is no `async_operations` platform table yet, so the match analysis record itself is the pollable operation resource (same pattern as job ingestions). `candidate_profiles` has no version column, so profile versioning is expressed through a computed fingerprint plus `updated_at`.

## Goals / Non-Goals

**Goals:**
- Compute an explainable, reproducible overall match score in deterministic Laravel code from trusted profile and confirmed-opportunity data
- Produce independent per-requirement results with states `matched`, `partial`, `gap`, `unknown` (unknown distinct from gap)
- Preserve required-vs-preferred importance end to end and apply the approved weights/factors/formula
- Expose category scores, matches, uncertain items, gaps, and suggestions (MATCH-002)
- Attach non-duplicative evidence references to each result
- Use AI only for bounded semantic comparisons, never for the final score and never mutating trusted data
- Store fingerprints and source versions; detect staleness; keep old snapshots immutable; recalculate creates new snapshots (MATCH-006)
- Expose a minimal async API with ownership authorization and RFC 9457 problem details
- Deliver the Career Intelligence Brief UI per the premium design system, WCAG 2.2 AA
- Reconcile the database docs to the normalized design (user-approved direction)

**Non-Goals:**
- Clarification questions and answers (separate `clarification-workflow` change; reserved endpoint untouched)
- CV tailoring, cover letters, resume generation
- Profile rewriting, enrichment, or auto-adding skills
- Company research, interview preparation, application tracking
- Job ranking, recommendations, or multi-opportunity comparison
- Learning roadmap recalculation
- Notifications and quota enforcement (later platform change)
- New `async_operations` platform table (later Platform change; analysis is the operation)
- New external dependencies

## Decisions

### Decision 1: Normalized matching tables instead of merged JSON

Three new tables replace the conflicting `opportunity_analyses` merged-JSON design from MLD/MCD/IMPLEMENTATION_PLAN and follow both the `config.yaml` planned tables and the implemented normalization precedent (`job_requirements`, `job_opportunity_skills`):

**`match_analyses`** — one row per analysis/snapshot; also the pollable operation.

| Column | Type | NULL | Notes |
|--------|------|------|-------|
| `id` | BIGINT UNSIGNED | NO | PK |
| `candidate_profile_id` | BIGINT UNSIGNED | NO | FK → candidate_profiles.id ON DELETE CASCADE |
| `job_opportunity_id` | BIGINT UNSIGNED | NO | FK → job_opportunities.id ON DELETE CASCADE |
| `status` | VARCHAR(30) | NO | `queued`, `processing`, `completed`, `failed` |
| `operation_key` | CHAR(64) | NO | Stable idempotency key (SHA-256) |
| `overall_score` | SMALLINT UNSIGNED | YES | 0–100, set only on completion |
| `evidence_coverage_score` | SMALLINT UNSIGNED | YES | 0–100, set only on completion |
| `required_count` | SMALLINT UNSIGNED | YES | Requirement counts at completion |
| `preferred_count` | SMALLINT UNSIGNED | YES | |
| `matched_count` / `partial_count` / `gap_count` / `unknown_count` | SMALLINT UNSIGNED | YES | Per-state counts at completion |
| `profile_fingerprint` | CHAR(64) | NO | SHA-256 of canonical profile snapshot |
| `opportunity_fingerprint` | CHAR(64) | NO | SHA-256 of canonical requirement snapshot |
| `profile_updated_at` | DATETIME | YES | Profile `updated_at` at analysis time |
| `opportunity_updated_at` | DATETIME | YES | Opportunity `updated_at` at analysis time |
| `algorithm_version` | VARCHAR(30) | NO | From `config/matching.php` |
| `scoring_version` | VARCHAR(30) | NO | From `config/matching.php` |
| `classifier_schema_version` | VARCHAR(30) | NO | From `config/matching.php` |
| `failure_code` | VARCHAR(100) | YES | Stable problem code |
| `failure_reason` | TEXT | YES | Redacted, internal only |
| `request_id` | VARCHAR(64) | YES | Propagated `X-Request-ID` |
| `queued_at` / `processing_started_at` / `completed_at` / `failed_at` | DATETIME | YES | |
| `created_at` / `updated_at` | TIMESTAMP | NO | |

Indexes: FK(candidate_profile_id), FK(job_opportunity_id), UNIQUE(candidate_profile_id, operation_key), INDEX(job_opportunity_id, status, created_at).

**`match_scores`** — one row per category per analysis (transparent category scoring).

| Column | Type | NULL | Notes |
|--------|------|------|-------|
| `id` | BIGINT UNSIGNED | NO | PK |
| `match_analysis_id` | BIGINT UNSIGNED | NO | FK → match_analyses.id ON DELETE CASCADE |
| `category` | VARCHAR(30) | NO | `required_skills`, `preferred_skills`, `evidence`, `experience_education`, `language_soft` |
| `weight` | DECIMAL(4,3) | NO | 0.500 / 0.200 / 0.150 / 0.100 / 0.050 |
| `score` | SMALLINT UNSIGNED | NO | Category score 0–100 |
| `achieved_points` | DECIMAL(8,2) | NO | For X/Y transparency in the UI |
| `total_points` | DECIMAL(8,2) | NO | |

Indexes: UNIQUE(match_analysis_id, category), FK(match_analysis_id).

**`match_findings`** — one row per evaluated requirement (the per-requirement results).

| Column | Type | NULL | Notes |
|--------|------|------|-------|
| `id` | BIGINT UNSIGNED | NO | PK |
| `match_analysis_id` | BIGINT UNSIGNED | NO | FK → match_analyses.id ON DELETE CASCADE |
| `source_type` | VARCHAR(30) | NO | `job_requirement`, `job_opportunity_skill` |
| `source_id` | BIGINT UNSIGNED | NO | Id in the source table |
| `requirement_text` | VARCHAR(500) | NO | Snapshot of original requirement content at analysis time |
| `requirement_label` | VARCHAR(255) | YES | e.g. normalized skill name |
| `importance` | VARCHAR(20) | NO | `required` \| `preferred` (snapshot) |
| `category` | VARCHAR(30) | YES | Classified category for unstructured requirements |
| `match_state` | VARCHAR(20) | NO | `matched`, `partial`, `gap`, `unknown` |
| `factor` | DECIMAL(4,2) | NO | 1.00 / 0.50 / 0.20 / 0.00 |
| `matched_candidate_skill_id` | BIGINT UNSIGNED | YES | FK → candidate_skills.id (nullable) |
| `evidence_refs` | JSON | YES | Non-duplicative `[{type, id, label}]` referencing candidate skills/profile items |
| `justification` | TEXT | YES | Short deterministic or classifier justification |
| `confidence` | VARCHAR(20) | YES | Classifier confidence when available |
| `classifier_source` | VARCHAR(255) | YES | Provider/model/prompt version when classifier used |
| `display_order` | SMALLINT UNSIGNED | NO | Stable order for the UI |
| `created_at` / `updated_at` | TIMESTAMP | NO | |

Indexes: FK(match_analysis_id), INDEX(match_analysis_id, importance), INDEX(match_analysis_id, match_state).

Alternatives considered:
- **Merged `opportunity_analyses` JSON (MLD)**: fewer tables, single 1:1 row, no joins. Rejected because `MATCH-002` requires queryable per-requirement matches/gaps with filters and evidence references; JSON columns would require in-app parsing, block efficient filtering by state/importance, and diverge from `config.yaml` planned tables and the implemented `job_requirements`/`job_opportunity_skills` normalization. User approved the normalized direction.
- **One wide `match_analyses` with an embedded results array**: simplest read for the UI. Rejected for the same queryability and immutability reasons; per-row persistence also allows incremental DB constraints and indexes.

### Decision 2: Match analysis is the pollable operation (no new async_operations table)

Creation returns `202 Accepted` with the `MatchAnalysisResource` (which includes `status` and `Location`), mirroring the job-ingestion pattern. A dedicated `GET /api/v1/operations/{id}` platform resource is out of scope; the analysis resource doubles as the operation. `operation_key` (SHA-256 of user + opportunity + client idempotency key) provides idempotency.

### Decision 3: Deterministic-first pipeline with bounded semantic classifier

Engine flow inside one queued job:

1. **Authorize and load**: resolve `candidate_profile` and `job_opportunity` (already authorized by the controller policy), load trusted profile data (candidate skills + evidence, profile items, languages) and confirmed requirements (`job_requirements` + `job_opportunity_skills`).
2. **Fingerprint**: compute `profile_fingerprint` and `opportunity_fingerprint` from canonical, sorted serializations (field names excluded; only values that affect matching) via SHA-256.
3. **Deterministic evaluation**: for each requirement, evaluate against trusted data:
   - skill requirement → candidate skill state factor (verified 1.00, claimed 0.50 shown separately, learning 0.20, missing/rejected 0.00)
   - language requirement → profile `languages` mapping
   - experience/education → `profile_items` typed matching
   - soft-skill/unstructured → semantic classifier (below) or `unknown` when no trustworthy evidence
4. **Semantic classifier**: one bounded, batched, structured call covering only unstructured requirements (responsibility relevance, transferable experience, education equivalence, unstructured classification). Prompt contains only requirement text and the relevant trusted experience/project items, delimited as untrusted data. Output is schema-validated in Laravel; numeric values are ignored; anything invalid or missing marks the affected requirements `unknown` (never fabricated).
5. **Score**: category scores from per-requirement points; `overall_score = round(required*0.50 + preferred*0.20 + evidence*0.15 + experience_education*0.10 + language_soft*0.05)`; `evidence_coverage_score` computed separately.
6. **Persist atomically**: one DB transaction writes the analysis header, `match_scores`, and `match_findings`; status → `completed`. Any failure before commit leaves status `failed` with a stable `failure_code`; no partial trusted update is ever persisted.

### Decision 4: Fingerprints and staleness (MATCH-006)

Staleness is derived at read time by recomputing the two current fingerprints and comparing with the stored ones, assisted by `profile_updated_at`/`opportunity_updated_at` as a cheap pre-check. Old analyses are immutable; `POST /api/v1/matches/{id}/recalculate` creates a brand-new analysis row. The resource exposes `stale`, plus the stored versions.

### Decision 5: AI stays behind the CvAnalyzer-style abstraction

`Services/Contracts/RequirementClassifier` (or `SemanticClassifier`) defines `classify(array $requirements): ClassifierResult`; `OpenAiRequirementClassifier` implements it with Laravel AI SDK `agent()` + `JsonSchema`, mirroring `OpenAiCvAnalyzer`. Laravel validates enums, references, length limits, and business rules before use. Tests default to a fake classifier; live provider tests are opt-in and isolated. Every call records provider, model, prompt version, latency, token counts, response id, and status in the AI audit column set (classifier_source + metrics).

### Decision 6: Scoring configuration

`config/matching.php` holds weights (0.500/0.200/0.150/0.100/0.050), factors (1.00/0.50/0.20/0.00), `scoring_version`, `algorithm_version`, `classifier_schema_version`, queue name, job timeout/retry/backoff, rate limits, max requirements per analysis, insufficient-profile thresholds (minimum trusted skills and minimum profile completion), and classifier model/timeout. All reads go through config, never `env()`.

### Decision 7: API surface

Under the existing `/api/v1` + `auth:sanctum` + throttle group:
- `POST /api/v1/opportunities/{id}/matches` → `202` + `MatchAnalysisResource` + `Location` (async creation)
- `GET /api/v1/opportunities/{id}/matches` → cursor-paginated analyses, newest first, latest flagged
- `GET /api/v1/matches/{id}` → full analysis (category scores, findings, evidence refs, versions, fingerprints, `stale`, warnings)
- `POST /api/v1/matches/{id}/recalculate` → `202` new analysis

The reserved clarification endpoint is untouched. Errors follow RFC 9457 via `app/Support/ProblemDetails`.

### Decision 8: Insufficient-profile gate

`config/matching.php` defines thresholds (e.g. profile completion >= 50 and at least one verified/claimed skill). Below thresholds, creation returns a `422`/`409`-style gate problem (`insufficient_profile`) or a gate analysis state; the UI shows the gate with a link to complete the profile rather than a misleading score.

### Decision 9: Frontend architecture

New feature `frontend/src/features/matching/`:
- `api/` — axios calls + `useMatchAnalysis` composable (Vue Query queries for list/latest/single, polling for status, mutations for create/recalculate)
- `types/` — API types mirroring the resources
- `components/` — `MatchBriefPage` (page), `ScoreAnchor`, `CategoryMeter`, `RequirementResultRow`, `RequirementFilterBar`, `MatchStatusPanel`, `StaleNotice`, `InsufficientProfileGate`, `MatchErrorState`
- route `/opportunities/:id/match` lazy-loaded inside `DefaultLayout`
- Reuse `components/ui/*` primitives and the problem-detail client; no new dependencies

UI state handling: loading skeletons, empty workspace, error/retry (no infinite loops), 401/419 centralized, processing polling with `aria-live` announcements, stale notice, insufficient-profile gate, filters (All/Matched/Partial/Gaps/Unknown × Required/Preferred) composed locally over the loaded findings.

### Decision 10: Observability

`request_id` propagated from HTTP through the job and provider calls. Logs exclude raw requirement text where it is PII-adjacent, provider payloads, and any score mid-computation. Metrics: analysis counts by status, queue lag/runtime/retries, classifier latency/tokens/schema failures, and security events (403/429).

## Risks / Trade-offs

- **AI cost and latency on unstructured requirements** → single batched call per analysis, bounded text length and requirement count (`config/matching.php`), deterministic-only analysis still completes with `unknown` states when the provider is unavailable.
- **Classifier hallucination** → strict schema validation, enum/reference limits, numeric fields ignored, `unknown` fallback, provenance stored, evaluation gates (schema validity >= 99%, classification accuracy >= 90%).
- **Determinism drift over time** → `scoring_version`, `algorithm_version`, and config values snapshotted per analysis; changes to weights require a config version bump and recalculation.
- **Staleness computation cost on every read** → cheap `updated_at` pre-check before fingerprint recompute; fingerprint only over fields that affect matching.
- **Queue worker dependency** → documented local requirement (`php artisan queue:work`); failed jobs retry with backoff and leave a safe failed state.
- **BOLA** → every owned lookup passes a policy; cross-user tests assert 404 with no existence leak.
- **Insufficient profile producing a misleading low score** → gate thresholds prevent analysis until minimum trusted data exists.

## Migration Plan

1. Add migrations for `match_analyses`, `match_scores`, `match_findings` (FKs, unique constraints, indexes, CHECK constraints on score ranges for MySQL).
2. Backfill: none — new tables only; no existing columns change.
3. Rollback: each migration has `down()` dropping the table; forward-fix documented in migrations.
4. Docs updated in the same change: `docs/database/MLD.md`, `MCD.md`, `IMPLEMENTATION_PLAN.md` replace the merged `opportunity_analyses` design with the normalized tables; `openspec/config.yaml` planned tables already match.
5. OpenAPI 3.1 updated with the four new endpoints and resources.

## Open Questions

- Exact gate thresholds (profile completion, minimum trusted skills) and rate-limit numbers for `config/matching.php` — defaults proposed in design, confirm during `/opsx:apply`.
- Whether the Opportunity detail page embeds the brief vs. a separate `/matches` tab — implement as a separate route first; embedding is a follow-up UI decision.
