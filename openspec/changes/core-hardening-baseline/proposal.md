# Proposal: core-hardening-baseline (Phase A)

## Why

The CareerPilot core (auth, profile, skills, CV/opportunity ingestion, matching, clarification, tailoring, PDF export) is implemented and stable, but an audit-driven pass is needed before building the remaining product modules. Known gaps exist: disabled accounts can still authenticate, CandidateSkill ownership relies only on implicit query scoping without the policy the spec requires, cleanup endpoints validate inline with structural holes, resume `version_no` never increments past 1, staleness is enforced against match analyses instead of the resume's own snapshots, clarification auditing records only acceptance events, and the dashboard route renders a page with no error/retry handling while a dead duplicate dashboard file exists. Closing these now is cheap; closing them after Applications/Interviews/Learning are built on top would be expensive.

## What Changes

- **Disabled account authentication protection**: `LoginUserAction` currently blocks only `Suspended`; a `Disabled` account can log in. Add login rejection with HTTP 403, problem code `account_disabled`, checked only after credential verification (anti-enumeration preserved). Add central rejection of already-authenticated requests from non-active accounts so existing sessions die once an account is disabled. Email verification, password reset, and active-user flows remain untouched.
- **CandidateSkill authorization formalization**: implement the `CANDIDATE-SKILLS-009` policy requirement that is specified but not implemented as a real policy. Add a `CandidateSkillPolicy` wired through the controller as defense-in-depth on top of existing profile-scoped lookups, preserving established 404 cross-user semantics. Extend regression coverage to evidence update/remove endpoints.
- **Profile cleanup endpoint validation**: replace inline `$request->validate()` in `ProfileDuplicateController` with Form Requests. Validate `keep_id` (required integer), `duplicate_ids` (required non-empty array, distinct, must not contain `keep_id`), and `allow_possible` (boolean) for item merge; equivalent structural validation for skill merge and language dedupe. Ownership always derives from the authenticated user's profile (never client-supplied profile ids). Problem Details behavior unchanged.
- **Resume versioning finalization**: `CreateResumeAction` hardcodes `version_no = 1`. Compute the next version per (candidate profile, opportunity) under lock so approved/drafted history yields 1, 2, 3... Keep the no-global-unique rule from migration `2026_08_24_235959_fix_resumes_unique_for_versioning` (reversible, existing rows survive). Ensure list ordering is newest-version-first, keep `version_no` exposure in resources, and confirm an approved previous version does not block creating a new draft.
- **Resume staleness and approval enforcement**: approval already returns 409 `resume_stale`, but staleness is computed against the latest match analysis rather than the resume's own snapshots, and tailoring does not refresh the resume's snapshot fingerprints. Move draft staleness to fingerprint comparison of the resume's own `profile_snapshot`/`opportunity_snapshot` against current source data; refresh those snapshots on create/tailor/content-regeneration. A stale draft cannot be approved; approved resumes stay immutable.
- **Deterministic tailoring relevance**: `TailoringAnalyzer` already prefers `MatchFinding.tailoring_relevance` and falls back to deterministic scoring over trusted data. Audit and lock this behavior with tests: fallback determinism, no invented facts or source IDs, traceability preserved, and a minimum content floor when relevance data is sparse (CV never becomes empty).
- **Clarification completeness audit**: `ApplyProposalAction` supports only `candidate_skill` targets; `profile_item` proposals fail with `proposal_not_supported`. Audit which proposal targets can be safely extended with the existing architecture. Extending apply to experience/education/language/basic fields requires new trusted mutation paths (provenance, concurrency, completion updates) — document the exact limitation and defer unless support already exists structurally. Trusted data is never mutated directly from an AI answer (unchanged invariant).
- **Clarification audit events**: only `proposal_accepted` writes `clarification_audit_events` today, and the table has no event-kind discriminator. Add an append-only event kind and record: question created, answer submitted, proposal generated, proposal accepted/rejected, question skipped, proposal apply failure. No raw provider output or secrets stored.
- **Dashboard baseline**: delete the empty dead `features/dashboard/pages/DashboardPage.vue`; keep `HomePage` at `/` as the dashboard baseline surfacing profile completion, CV ingestion attention, opportunities, matching state, and resumes where relevant. Add loading, empty, error, and retry states so one failed request cannot blank the dashboard. No Applications/Tasks/Interviews widgets.
- **X-Request-ID consistency**: `nextRequestId()` is duplicated across three feature API modules. Centralize generation/injection in the shared API client layer and remove per-feature duplication.
- **File-storage architecture decision (documentation only)**: decide between a generic FILE entity (Option A) and purpose-specific document entities (Option B) for future application documents/exports/account export. Document direction, rationale, migration implications, ownership/provenance implications, and the fate of the dangling `resumes.file_id` column. No Phase A migrations for this.
- **Interview model decision (design only)**: recommend among dedicated interviews table / tasks-with-metadata / task-plus-interview-record for Phase D. Documentation only; no implementation.

## Capabilities

### New Capabilities
- `dashboard-baseline`: authenticated dashboard surface (`HomePage`) reliably showing core state (profile completion, CV attention, opportunities, matches, resumes) with mandatory loading/empty/error/retry states; removal of the dead duplicate dashboard page.
- `profile-duplicate-cleanup`: duplicate report and merge/cleanup endpoints (`/profile/duplicates`, `/profile/cleanup/items|skills|languages`) with structural validation, ownership scoping, and consistent problem-details errors.

### Modified Capabilities
- `user-authentication`: AUTH-001 gains explicit disabled-account rejection (403 `account_disabled`) after credential check, preserving anti-enumeration, email verification, and password reset behavior.
- `user-session`: adds requirement that requests from authenticated sessions of non-active accounts are centrally rejected (403 `account_disabled`) via middleware.
- `cv-tailoring`: version numbering auto-increments per opportunity; re-tailoring/regeneration refreshes the resume's own snapshot fingerprints; stale drafts cannot be approved (409 `resume_stale`); deterministic relevance fallback keeps a minimum content floor.
- `clarification-workflow`: adds append-only audit event coverage for the full question/proposal lifecycle and explicitly scopes which proposal target types can mutate trusted data today.
- `api-foundation`: FQ-FRONT-001 refined — request-ID generation moves into the centralized API client; features stop duplicating it.

## Impact

- **Backend code**: `app/Domain/Identity/Actions/LoginUserAction.php`, new identity middleware, `app/Http/Middleware/*` registration, `app/Domain/Skills/Policies/CandidateSkillPolicy.php` (new) + `CandidateSkillController`, `app/Http/Requests/Api/V1/Profile/*` (new Form Requests) + `ProfileDuplicateController`, `app/Domain/Resumes/Actions/CreateResumeAction|TailorResumeAction|UpdateResumeContentAction|ApproveResumeAction`, `TailoringAnalyzer`, clarification actions/listeners + `clarification_audit_events` migration (event-kind column).
- **Frontend code**: `src/features/home/pages/HomePage.vue` (error/retry states), deletion of `src/features/dashboard/pages/DashboardPage.vue`, `src/api/client/*` (centralized request ID), feature API modules dropping local `nextRequestId`.
- **API surface**: error contract addition (`account_disabled`); no route additions/removals except none; response shapes unchanged except corrected version/staleness values.
- **Database**: one small migration adding a nullable/typed event-kind column to `clarification_audit_events` (append-only table; rollback documented). Resume versioning uses existing columns; no unsafe unique constraints added. No files-table work in Phase A.
- **Docs**: `docs/database/README.md`, `MCD.md`, `MLD.md` updated for the storage decision (deprecate generic FILE model) and the Phase D interview model recommendation.
- **Security**: closes a real authentication bypass class (disabled accounts), strengthens BOLA posture on candidate skills, tightens server-side validation on mutating cleanup endpoints, preserves anti-enumeration and rate limits.
- **Tests**: regression coverage added per area (auth, skills authz incl. evidence endpoints, cleanup validation/cross-user, resume versions/staleness/immutability, relevance fallback, clarification audit/skip-reject events, dashboard failure states).
- **Out of scope (unchanged stable systems)**: authentication architecture, ProfileIdentityService, skills state machine, CV ingestion pipeline, opportunity ingestion pipeline, match engine math, PDF export internals, Vue Query architecture, Problem Details/request-ID backend infrastructure, Company Research, Application Tracking, Tasks, Interviews implementation, Learning Roadmap, Notifications, DOCX, Admin/AI Operations, quotas, account export/deletion, deployment/integration work.

## Acceptance Criteria

1. Disabled account login → HTTP 403, `code: account_disabled`; wrong-password path unchanged (anti-enumeration intact); active users unaffected; password reset/email verification unaffected.
2. Authenticated request from a disabled account's session → 403 `account_disabled` centrally; active sessions unaffected.
3. User A cannot view/update/archive/restore/delete or manage evidence on User B's candidate skill (404 semantics preserved).
4. Cleanup merges reject malformed payloads with 422 validation errors: missing/empty `duplicate_ids`, duplicate entries in `duplicate_ids`, `keep_id` inside `duplicate_ids`, non-boolean `allow_possible`; cross-user IDs rejected; ownership always server-derived.
5. Creating a resume after an approved/draft history yields incrementing `version_no` per opportunity; listing orders newest first; existing single-version data survives; migration reversible; approved previous version does not block a new draft.
6. Draft resume whose source changed → approve blocked with 409 `resume_stale` and the agreed message; regeneration/tailoring clears staleness by refreshing snapshots; approved resumes immutable.
7. Tailoring uses MatchFinding relevance when present; otherwise deterministic fallback using trusted data only; sparse data still produces a sensible minimum CV, never empty content, never invented facts/source IDs.
8. Clarification lifecycle emits append-only audit events for created/answered/generated/accepted/rejected/skipped/apply-failure without raw provider payloads or secrets.
9. Dashboard shows each panel's failure independently with retry; one failing API call never blanks the page; dead dashboard file removed.
10. Frontend sends `X-Request-ID` from the centralized client; no per-feature duplication remains.
11. Storage and interview-model decisions documented and stable for future phases.
12. Quality gates pass: backend Pest/Pint/PHPStan/route-list/migrate-status; frontend type-check/lint/unit/build; `git diff --check`.

## Risks and Assumptions

- Moving draft-staleness comparison to the resume's own snapshots changes observable staleness timing for pre-existing drafts; acceptable because it aligns behavior with the approved intent ("regenerate before approving") and existing drafts are MVP-local test data.
- Central session rejection could lock out legitimate users if status transitions misfire; mitigated by only rejecting non-`active` statuses and keeping `/me` reachable for clean client-side sign-out messaging (design detail in design.md).
- Clarification ProfileItem apply extension is expected to be deferred; if the audit shows existing structural support is sufficient, it will be delivered within this change's tasks with full provenance/concurrency/completion requirements.
- No dependency upgrades; no new packages required.
