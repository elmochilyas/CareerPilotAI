## Context

The `deterministic-match-engine` change delivered `Matching` domain actions (`CreateMatchAnalysisAction`, `RecalculateMatchAnalysisAction`, `ShowMatchAnalysisAction`, `ListMatchAnalysesAction`, `MarkMatchAnalysisFailedAction`), the `match_analyses`/`match_scores`/`match_findings` tables, a `FingerprintService`/`StalenessService`, and the match-brief API and UI. The engine scores deterministic factors where `verified` skills map to 1.00, `claimed/needs clarification` to 0.50 (shown separately), `learning` to 0.20, and `missing/rejected` to 0.00. `match_findings` carry a `match_state` of `matched`, `partial`, `gap`, or `unknown` and `evidence_refs` JSON pointing at trusted profile sources.

High-impact uncertainty today is a dead end: a `gap`/`partial` finding on a required skill shows the candidate "missing" even when they actually possess it, because the engine has no way to learn that. `openspec/config.yaml` reserves `MATCH-004` (high-impact uncertainty creates clarification questions, not silent changes), `MATCH-005` (profile-changing answers require evidence or explicit no-evidence acknowledgement), the tables `clarification_questions`/`clarification_answers`, the invariant "one answer per clarification question", and the endpoint `POST /api/v1/clarifications/{id}/answer`. The matching and ingestion changes explicitly deferred all of this to the `clarification-workflow` change.

Existing patterns to reuse:

- **Preview/confirm**: `Opportunities` domain's `GeneratePreviewAction` + `ConfirmOpportunityAction` (DB transaction, `lockForUpdate`, `version_token` parameter, idempotent re-confirm) is the reference for proposal review followed by a transactional trusted mutation.
- **Idempotency**: `Matching`'s `CreateMatchAnalysisAction` hashes `user_id|opportunity_id|operation_key`; `RecalculateMatchAnalysisAction` uses `recalculate:<analysisId>`.
- **Staleness**: `Matching`'s fingerprint/staleness service marks analyses stale when trusted source versions change; the frontend brief already distinguishes `stale` vs `latest`.
- **Trusted source versions**: `candidate_skills` carries `state` (claimed/verified/learning/rejected/archived), `proficiency_level`, and `evidence` JSON. `candidate_profiles` has no version column; change detection intentionally lives in `Matching`.

## Goals / Non-Goals

**Goals:**

- Turn high-impact uncertain findings (`partial`/`gap` with factor 0.00 or 0.50 and no trusted answer) into a bounded set of targeted clarification questions the candidate answers one at a time.
- Persist answers as candidate input with explicit status, never as silently trusted data.
- Produce a reviewable proposal for every profile-changing answer, accept/edit/skip it, and only on explicit acceptance apply a trusted profile mutation transactionally.
- Require evidence or an explicit no-evidence acknowledgement for profile-changing answers (MATCH-005); never let missing skills become verified without either.
- Mark the owning `MatchAnalysis` stale via the existing fingerprint service when a trusted mutation lands, and leave recalculation to the existing `RecalculateMatchAnalysisAction`/user flow.
- Keep the final numeric score deterministic Laravel code at all times (MATCH-001).
- Deliver the reserved `/api/v1/clarifications/{id}/answer` endpoint plus the additive question-list and review endpoints, all ownership-scoped with RFC 9457 errors.
- Add a frontend clarification flow with a backend-driven entry point in the match brief and a dedicated flow route (one-question steps, review panel, full state coverage) per the design system.

**Non-Goals:**

- Changing match weights, factors, formula, or how `match_state` is computed.
- Any silent or AI-originated profile mutation without explicit candidate approval.
- Chat-style clarification UI or free-form LLM question authoring.
- Third-party claim verification (link verification is future `skill-evidence` work).
- Applications, learning roadmap, or interview flows.
- New profile version column on `candidate_profiles` (change detection stays in `Matching`).
- New dependencies, services, or infrastructure.

## Decisions

### D1. Clarification belongs to a new `Clarification` domain owned by `Matching` behavior

A new `backend/app/Domain/Clarification/` domain (`Actions`, `Data`, `Enums`, `Events`, `Policies`, `Services`) implements the workflow. The `Matching` domain exposes its findings and fingerprint/staleness services; `Clarification` consumes them and produces trusted mutations through `Skills` and `Profile` actions.

**Why:** one owning domain per capability, clear boundary between "matching output" and "clarification orchestration", and reusable by other flows later. Alternatives considered: (a) extending `Matching` — rejected, it would bloat matching with profile-write responsibilities and break the thin-domain rule; (b) extending `Skills` — rejected, clarification is driven by match findings, not by skills alone.

### D2. Two tables: `clarification_questions` and `clarification_answers` (normalized, not JSON)

```text
clarification_questions
  id            (ulid PK)
  match_analysis_id  (FK -> match_analyses.id, indexed)   // owning analysis
  match_finding_id   (FK -> match_findings.id, indexed)   // nullable for non-finding context
  question_no   (smallint unsigned)
  question_type (enum string: yes_no, yes_no_with_details, text, select, number)
  prompt        (string(500))
  detail        (string(1000), nullable)   // why we ask, evidence basis
  template_key  (string(100))              // versioned deterministic template identifier
  options_json  (json, nullable)           // for select
  unit          (string(30), nullable)     // for number
  status        (enum string: pending, answered, skipped, expired)
  ai_metadata   (json, nullable)           // assistant ranking/reword provenance
  created_at, updated_at

clarification_answers
  id            (ulid PK)
  user_id       (FK -> users.id, indexed)           // ownership column (defense in depth)
  question_id   (FK -> clarification_questions.id, unique)   // one answer per question
  answer_type   (enum string: yes, no, no_with_ack, text, select_option, number)
  value         (text)                              // raw candidate input
  acknowledged_no_evidence (bool default false)     // explicit no-evidence acknowledgement
  status        (enum string: pending, accepted, rejected, skipped, expired)
  proposal_id   (FK -> clarification_proposals.id, nullable)   // link to accepted/rejected proposal
  created_at, updated_at
```

The proposal record is stored in a third small table `clarification_proposals` (target entity type, target entity id, field, before, after, origin answer id, status `proposed/accepted/rejected/skipped`, immutable once accepted). This keeps answers (candidate input) strictly separate from proposals (the proposed trusted mutation).

**Why:** normalized relational data per the database standards; the unique `(question_id)` enforces the config invariant "one answer per clarification question" at the DB level. JSON is used only for bounded option lists and AI provenance, not for core state. Indexes cover every FK and the `(match_analysis_id, status)` filter.

### D3. Deterministic-template-first question generation

`BuildQuestionSessionAction` takes an analysis + its eligible findings and produces questions:

1. Eligibility filter: `match_state IN (partial, gap, unknown)` with `factor IN (0.00, 0.50)`, the requirement is required or preferred, no already-answered question for the same finding, and no trusted answer already present (`candidate_skills` verified, or an accepted evidence-bearing answer exists). Reviewed decision: `unknown` is treated as eligible (it is genuinely uncertain and resolvable), and the preferred-skill exclusion also covers `gap`/`unknown` — a low-impact preferred finding that would be excluded would otherwise generate a question with no plausible acceptance benefit. Findings whose category resolves to no template (`category` null) stay excluded.
2. Template resolution: a registry of versioned templates keyed by `(finding_type, requirement_importance)` — e.g. "skill-missing", "skill-claimed-no-evidence", "experience-ambiguous" — each producing a question type and prompt. Reviewed decision: an `unknown` finding resolves to the same gap template types as `gap` for the same category (`SkillMissing`/`ExperienceMissing`/`LanguageMissing`/`EvidenceMissing`); `null` remains for unclassified findings. The engine picks the best template deterministically.
3. **Ordering/cap**: order by impact (importance, then factor gap), cap at 3 questions per pass, never duplicate a finding. Duplicate-clarification gate < 5% is measured at the template level.
4. The `ClarificationAssistant` (AI) may then **rank and reword** already-eligible questions under schema validation (AI-003); it never adds, removes, or changes eligibility, never writes a question to the DB, and never sees the final score. Its structured output must pass the assistant schema or the deterministic set is used as-is.

**Why:** MATCH-004 wants targeted questions, not silent changes, and MATCH-001 forbids LLM scoring influence. Deterministic templates guarantee a safe, reproducible, explainable baseline; the AI role is a bounded, validated refinement. Alternatives considered: (a) free-form LLM question generation — rejected, violates explainability and risks invented requirements; (b) no AI at all — acceptable fallback and MVP default, but the reserved AI metadata column lets us activate the assistant behind the evaluation gate.

### D4. Answer lifecycle is candidate input with explicit trust transition

Answers persist with status `pending` the moment the candidate submits. Trusted mutation happens only after an explicit acceptance of a proposal:

- `yes` + evidence URL/snippet → proposal adds/verifies skill with evidence → on acceptance, `Skills` action promotes the `candidate_skill` to `verified` with the evidence and origin answer id.
- `yes` without evidence + `acknowledged_no_evidence = true` → proposal promotes to `claimed` (0.50, shown separately) with the explicit acknowledgement recorded — never `verified`.
- `no` → proposal marks the skill `rejected` (or records the gap as acknowledged) so the engine stops asking; on acceptance the skill is rejected/archived.
- `text`/`select`/`number` → proposal edits the relevant profile field with the answer value; on acceptance a `Profile` action applies it with provenance.
- `skipped` at the question level → question status `skipped`, no proposal, no mutation.

Accepted proposals are immutable (like approved resume versions); the answer's `status` becomes `accepted` and is linked to the proposal. Rejected proposals leave the answer `pending`/`rejected` without mutation.

**Why:** this is the trust boundary MATCH-005 demands — candidate input is never trusted by default; only an explicit human acceptance of a concrete proposal turns it into trusted profile data, with evidence or explicit no-evidence acknowledgement recorded.

### D5. Staleness via fingerprint, never direct score mutation

`ApplyProposalAction` runs inside a DB transaction and performs only the trusted profile mutation (`Skills`/`Profile` action) plus proposal/answer status updates. After commit it dispatches a queued job that calls `Matching`'s `FingerprintService`/`StalenessService` to mark the owning `MatchAnalysis` stale. The score is never recomputed in that transaction. The brief already renders stale analyses with a recalculation CTA; no new matching endpoint is needed.

**Why:** MATCH-006 requires every recomputation to snapshot exact source versions. Reusing fingerprint-based staleness keeps one source of truth for "when is a match outdated" and avoids embedding scoring logic in the clarification path.

### D6. API surface (additive, all ownership-scoped)

```text
GET  /api/v1/matches/{id}/clarifications      -> 200 list of question items (session) or 404
POST /api/v1/clarifications/{id}/answer       -> 201 created answer (reserved shape, config.yaml)
POST /api/v1/clarifications/{id}/review       -> 200/202 proposal result for the answer
POST /api/v1/clarifications/{id}/skip         -> 200 question skipped
```

- All routes behind `auth:sanctum` + CSRF; all lookups scoped by `ClarificationQuestionPolicy`/`ClarificationAnswerPolicy` with cross-user 404 (BOLA).
- Errors: RFC 9457 via `app/Support/ProblemDetails` — `clarification_question_not_found`, `clarification_session_expired`, `answer_already_exists`, `proposal_not_reviewable`, `invalid_evidence_url`, `analysis_not_found`. Rate limited per the baseline (general writes 60/min/user).
- The review endpoint is synchronous when the mutation is cheap (skill promote with evidence URL); future heavy verification (link fetch) would become 202 + operation polling — explicitly not implemented here.
- **Session signal (no new endpoint):** the session resource (`GET .../clarifications` and `POST .../clarifications` generate responses) embeds `generable_count` — how many more questions a generate call could still create. `ListClarificationsAction` computes it via `BuildQuestionSessionAction::eligibleCount()`: findings already planned as open questions are no longer eligible, so the count naturally drops to 0 after a generate pass. The frontend uses it as the source of truth for its entry CTA and never shows a CTA that leads to a dead end.

**Why:** the reserved endpoint shape is honored; `review` and `skip` are additive and required for the review-then-apply and one-question-at-a-time rules. No approval already exists for changing the reserved route, so we extend rather than modify.

### D7. Frontend: dedicated clarification route with a backend-driven entry CTA

- New feature folder `frontend/src/features/clarification/` (api, query hooks via TanStack Vue Query factory conventions, components, types).
- New route `opportunities/:id/match/clarifications` (name `opportunities-match-clarifications`, lazy-loaded, `requiresAuth`) hosts the full `ClarificationPage` (header reuse, status panel, error/insufficient-profile gates, and the `ClarificationFlow`); it reuses `useMatchAnalysis` and renders the completed-analysis brief header with a back link to the match brief.
- `MatchBriefPage.vue` shows a backend-driven `ClarificationEntryCard` after `MatchAtAGlance`. The card is the single source of truth for the entry point and has four states driven by the session resource, never a dead-end:
  1. actionable open questions → "Answer {n} quick questions…" → navigates to the clarification route;
  2. no pending but reviewable answers → "You have a pending profile change to review." → navigates to the clarification route;
  3. nothing actionable but `generable_count > 0` → "Generate questions" → navigates to the clarification route where the empty state offers the generate action;
  4. nothing actionable and `generable_count = 0` → hidden.
- Flow: one question card at a time with progress (e.g. "Question 1 of 3"), question type-aware inputs (yes/no, yes/no+evidence, text, select, number), a details disclosure explaining the evidence basis, and skip. Submitting `answer` moves to the review step: a summary of the proposed profile change (target, before → after, evidence or acknowledgement) with Accept / Edit / Skip. On accept, invalidate matching + skills queries so the stale indicator and recalculated brief refresh. The empty state offers "Generate questions" (and "Start a fresh session" in the expired state) via `POST .../clarifications`, then refetches the session.
- States: loading skeleton, empty (no eligible questions), error with retry, expired-session message (question status expired → new session), success confirmation. Accessible: keyboard navigation, visible focus, `role="group"`/`aria-live` announcements, contrast per WCAG 2.2 AA, mobile-first at ~390 px.

**Why:** the brief stays focused on results while the clarification flow gets a stable destination with its own loading/error/empty handling and a backend-driven CTA that never leads nowhere. Reviewed decision: a dedicated page supersedes the earlier inline-drawer idea (originally rejected "for MVP" because the entry point was the brief; extracting to a route now is cheap, keeps the brief lean, and gives the flow a reliable refresh/back story). The backend remains the source of truth for CTA state via `generable_count`.

### D8. No new dependencies

Everything uses existing Laravel (validation, policies, queue, DB, `app/Support/ProblemDetails`) and existing frontend tooling (TanStack Vue Query, UI kit, design tokens). The AI assistant, when active, uses the Laravel AI SDK with a faked provider in default tests.

### D9. Security, privacy, and observability

- **Ownership**: every lookup starts from `auth()->user()`; policies applied in controllers; route binding never used as authorization. Cross-user tests prove guessed IDs 404.
- **Privacy**: the assistant prompt receives only the minimal candidate-owned context (finding, requirement label, evidence basis) with untrusted content delimited (AI-004); never passwords, cookies, tokens, or unrelated profile data. Logs redact CV text and PII; no raw provider payloads persisted.
- **Prompt injection**: job descriptions and evidence text are untrusted data, delimited and instruction-ignored; assistant output passes strict schema + business validation before use (AI-003).
- **Quotas**: answer/review writes respect the general-write rate limit; AI metadata recorded per AI-006.
- **Observability**: request IDs propagate to jobs; audit event written when a proposal is accepted (origin answer, target, before/after); metrics for clarification count, acceptance rate, no-evidence-ack rate, assistant fallback rate, and useful-clarification rate (>= 80%) feeding the evaluation gates.

## Risks / Trade-offs

- **Trust boundary violation** (an answer auto-verifying a skill) → [Risk] Mitigation: accepted-proposal-only mutation is the only write path; policies + architecture tests assert no other code promotes skills; dedicated tests prove no-evidence never yields `verified`.
- **LLM output drifting or hallucinating a question** → [Risk] Mitigation: deterministic templates are the source of questions; the assistant only ranks/rewords under a strict schema; on validation failure or provider error the deterministic set is used and the session records `ai_metadata.fallback_reason`; zero partial updates on failure.
- **Stale analyses after mutation** (brief shows old score) → [Risk] Mitigation: stale marking happens on a committed, dispatched job; the brief already distinguishes stale vs latest and prompts recalculation; tests assert staleness after accept.
- **Too many/duplicate questions** (gate < 5% duplicate, at most 3/pass) → [Risk] Mitigation: unique `(question_id)` per answer plus deterministic de-duplication by finding; template-level duplicate measurement.
- **Expired sessions mid-flow** → [Risk] Mitigation: question `expired` status and an explicit expired state in the UI that offers a fresh session; idempotent answer creation returns `answer_already_exists` instead of duplicating.
- **Evidence URL becomes unreachable** → [Risk] Mitigation: the URL is stored as provenance but never fetched in this change (fetching is future work), so acceptance is not blocked by reachability.
- **Race between two answers for one question** → [Risk] Mitigation: DB unique constraint on `(question_id)`; the action catches the constraint violation and returns the existing answer.

## Migration Plan

1. Add migrations for `clarification_questions`, `clarification_answers`, `clarification_proposals` with FKs, indexes, unique `(question_id)`, and enum-string columns; each migration includes rollback (drop tables in reverse dependency order).
2. Deploy backend first (new domain, routes, policies) — no breaking API changes; then frontend build deploy.
3. Rollback: reverse migrations restore prior schema; feature is additive so an older frontend still renders the brief without the clarification entry.

## Open Questions

- Should the assistant activation be behind the `useful clarification rate >= 80%` gate, or should MVP ship with the deterministic templates only and enable the assistant in a later AI-operations change? Default for MVP: deterministic only, `ai_metadata` reserved.
- Should `clarification_proposals` be a separate table or JSON on answers? Decision here: separate table for audit/immutability; confirm with reviewers.
- Do we allow editing the proposed value before accept (e.g. changing a years-of-experience number)? Decision here: yes for number/text values, no for skill-verified state; confirm with reviewers.
