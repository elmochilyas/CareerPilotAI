# CareerPilot AI — Database Implementation Plan

## Rules

1. Work on `feature/<openspec-change-name>`, never directly on `main`.
2. Inspect existing migrations before generating new ones.
3. Create only tables owned by the active OpenSpec change.
4. Create parent tables before child tables.
5. Use explicit FK delete behavior.
6. Add factories, seeders, enums, casts, and Pest tests with the owning feature.
7. Never run `migrate:fresh` against a non-test database.
8. Update MCD/MLD references in the same PR when an approved schema decision changes.

## OpenSpec ownership

- `foundation-quality-baseline`: Laravel technical infrastructure only.
- `candidate-authentication`: `users`, password reset, sessions, Sanctum technical tables.
- `candidate-profile-core`: `candidate_profiles`, `candidate_preferences`, `educations`, `experiences`, `projects`, `certifications`, `candidate_languages`.
- `skills-and-evidence`: `skills`, `candidate_skills`, `skill_evidences`.
- `cv-ingestion-pipeline`: `stored_files`, `cv_imports`.
- `job-opportunity-ingestion`: `companies`, `opportunities`.
- `job-requirement-analysis`: `job_analyses`, `job_requirements`.
- `company-research-brief`: `company_research`.
- `deterministic-match-engine`: `match_analyses`, `match_findings`.
- `clarification-workflow`: `clarifications`.
- `resume-tailoring-versioning`: `resumes`, `resume_versions`.
- `resume-pdf-docx-export`: `resume_exports`.
- `application-tracking`: `applications`, `application_status_histories`, `application_notes`, `application_documents`.
- `learning-roadmap`: `learning_roadmaps`, `roadmap_items`.
- `tasks-reminders-interviews`: `tasks`, `interviews`.
- `interview-preparation`: `preparation_packs`, `mock_interview_sessions`, `mock_interview_turns`.
- `notifications-quotas-ai-audit`: `ai_runs`, `notification_preferences`, `notifications`.
- `security-observability-deployment`: normally no core business tables.

Quota tables are not part of the currently approved lean MCD/MLD and require a separately approved schema update before implementation.

## Dependency-oriented migration order

1. Framework technical tables
2. `users`
3. `candidate_profiles`
4. profile child tables
5. `skills`
6. `candidate_skills`
7. `stored_files`
8. `skill_evidences`
9. `cv_imports`
10. `companies`
11. `opportunities`
12. `company_research`
13. `job_analyses`
14. `job_requirements`
15. `match_analyses`
16. `match_findings`
17. `clarifications`
18. `resumes`
19. `resume_versions`
20. `resume_exports`
21. `applications`
22. application child tables
23. `learning_roadmaps`
24. `roadmap_items`
25. `tasks`
26. `interviews`
27. interview preparation tables
28. `ai_runs`
29. notification tables

This is a dependency order, not permission to create every table in one change.

## Required database tests

Each owning feature tests:

- successful creation;
- validation boundaries;
- required FKs;
- unique constraints;
- ownership isolation;
- prohibited cross-user links;
- delete behavior;
- enum casts;
- JSON casts;
- state transitions;
- transaction rollback on failure.

High-risk invariants requiring explicit tests:

- one profile per user;
- no duplicate candidate skill;
- CV import cannot confirm rejected data;
- one current analysis per opportunity;
- score remains within bounds;
- approved resume version is immutable;
- one application per opportunity;
- immutable application history;
- exact application-document source;
- one roadmap item per skill;
- task has at most one context;
- AI cannot verify a skill.
