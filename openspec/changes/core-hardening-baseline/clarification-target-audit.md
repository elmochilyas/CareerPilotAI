# Clarification target audit — Phase A (core-hardening-baseline)

Date: 2026-08-25
Audited files:
- `app/Domain/Clarification/Actions/BuildProposalAction.php`
- `app/Domain/Clarification/Actions/ApplyProposalAction.php`
- `app/Domain/Clarification/Enums/ClarificationTargetType.php` (`candidate_skill`, `profile_item`)
- `app/Domain/Clarification/Actions/BuildQuestionSessionAction.php`

Findings:

- Proposal generation today supports exactly one trusted-apply target: `candidate_skill`. `BuildProposalAction::execute` guards via `isSkillFinding` (RequiredSkills/PreferredSkills categories) and always sets `target_type = CandidateSkill`. The `else` branch never builds a profile_item proposal; non-skill findings throw `proposal_not_supported`.
- `candidate_skill` vs `profile_item` enum values exist in `ClarificationTargetType`, but `profile_item` is **never produced** by BuildProposalAction and therefore no trusted mutation path for it is exercised.
- `ApplyProposalAction::apply` accepts only `candidate_skill`. Any `profile_item` target fails immediately with HTTP 422 `proposal_not_supported` before touching trusted data.
- All skill mutations in `apply` go through validated domain actions (`CreateCandidateSkillAction`, `UpdateCandidateSkillAction`, `AddEvidenceAction`) inside one DB transaction. Concurrency is protected via `lockForUpdate`.

Conclusion / limitation (maps to CLAR-012):

- Extending apply to `experience`, `education`, `language`, or basic profile fields would require building new trusted mutation paths that:
  - show exactly what will change (before/after)
  - require explicit candidate approval (already exists)
  - preserve source/provenance
  - use `lockForUpdate` concurrency protection
  - recompute profile completion and mark stale analyses
  - record append-only audit events
- Those paths do not exist today. As specified for Phase A, this extension is **deferred**. Any future change that adds them SHALL implement all five requirements above and cover them with cross-user plus concurrency tests.
- `candidate_skill` remains the only supported proposal target in Phase A. Existing API contracts for unsupported targets (422 `proposal_not_supported`, no partial mutation) are covered via regression tests.
