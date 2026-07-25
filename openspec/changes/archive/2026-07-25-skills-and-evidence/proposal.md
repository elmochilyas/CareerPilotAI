## Why

CareerPilot's promise — "one trusted profile, many tailored applications" — requires structured skill data. Without a normalized skill inventory with states, evidence, and access controls, every downstream feature (matching, resume tailoring, gap analysis, learning roadmaps) cannot distinguish between a candidate's genuine expertise and unverified claims. The existing profile core creates profiles and items but has no skill representation. This change establishes the bounded skill-and-evidence capability that downstream features depend on, while enforcing the non-negotiable truthfulness rule: no skill reaches verified status without the candidate's explicit confirmed action and supporting evidence.

## What Changes

- Introduce `skills` and `candidate_skills` database tables (adding a `state` column to the MLD spec to resolve a contradiction)
- Add `skill_aliases` table for deterministic alias resolution
- Create CRUD API for the canonical skill catalog (read-only for candidates, seeded)
- Create stateful candidate-skill management with claimed, verified, learning, rejected, archived states
- Build deterministic state-transition enforcement with a dedicated service
- Create evidence management within candidate_skills (profile-item references and URL evidence stored as JSON)
- Build a modern Vue 3 Skills section within the existing profile page
- Add accessible skill search, add-flow, evidence linking, and archive/restore
- Keep profile completion unchanged — skills are separate
- Do not introduce CV ingestion, AI extraction, matching, or verification workflows

## Capabilities

### New Capabilities
- `skill-catalog`: Canonical normalized skill catalog with unique normalized names, optional category, active flag, and alias resolution
- `candidate-skills`: Stateful candidate skill inventory with proficiency, years experience, last-used date, state transitions, and archive/restore
- `skill-evidence`: Evidence linking for candidate skills — profile-item references (experience, project, education, certification) and URL evidence — stored as JSON within candidate_skills
- `skills-ui`: Vue 3 skills section integrated into the profile page with search, add flow, state chips, evidence linking, archive/restore, loading/empty/error/conflict states

### Modified Capabilities
- `profile-completion`: Explicitly unchanged — skills must not modify the documented 100% profile-completion calculation
- `profile-crud`: No changes to profile or profile-item endpoints; evidence references existing profile_items but does not modify them
- `profile-ui`: The profile page gains a Skills section; existing sections remain unchanged

## Impact

### Backend
- New files under `app/Domain/Skills/` (Actions, Data, Enums, Events, Policies, Services)
- New `SkillController` and `CandidateSkillController` in `app/Http/Controllers/Api/V1/`
- New Form Requests under `app/Http/Requests/Api/V1/Skills/`
- New API Resources under `app/Http/Resources/Api/V1/`
- New routes in `routes/api.php` under the authenticated `auth:sanctum` group
- Two new migrations: `create_skills_table`, `create_candidate_skills_table`
- Optional third migration: `create_skill_aliases_table` (depends on approved design)
- New models: `Skill`, `CandidateSkill`
- No new enums needed beyond the existing pattern — skill state, proficiency, and evidence type enums in `app/Domain/Skills/Enums/`
- Seeder for initial canonical skill catalog

### Frontend
- New feature folder `frontend/src/features/skills/` with api, components, composables, types subdirectories
- Skills section component integrated into the existing profile page (not a new route)
- Skill search combobox component
- Add-skill flow (search, select, set state, set proficiency, add evidence)
- Skill evidence linking UI (select existing profile items, add URLs)
- No new route — skills live within `/profile`
- No new Pinia store — TanStack Vue Query manages all skill server state
- No new npm dependencies

### Database
- `skills` table: id, name (VARCHAR 150), normalized_name (VARCHAR 150, UNIQUE), category (VARCHAR 100, nullable), is_active (BOOLEAN), timestamps
- `candidate_skills` table: id, candidate_profile_id (FK → candidate_profiles.id), skill_id (FK → skills.id), **state** (VARCHAR 30, resolving MLD contradiction), proficiency_level (VARCHAR 30), years_experience (DECIMAL 4,1, nullable), last_used_at (DATE, nullable), evidence (JSON, nullable), timestamps
- `skill_aliases` table (conditional): id, skill_id (FK), alias (VARCHAR 150, UNIQUE)
- UNIQUE(candidate_profile_id, skill_id) constraint
- CHECK constraints on state, proficiency_level, years_experience
- FK on candidate_profile_id with CASCADE delete
- FK on skill_id with RESTRICT delete
- Indexes on (candidate_profile_id, state), (normalized_name), (alias)

### API
- `GET /api/v1/skills` — search canonical skill catalog (query param, paginated)
- `GET /api/v1/skills/{skill}` — show one canonical skill
- `GET /api/v1/candidate/skills` — list authenticated candidate's skills
- `POST /api/v1/candidate/skills` — add a skill to candidate's inventory
- `GET /api/v1/candidate/skills/{candidateSkill}` — show one candidate skill with evidence
- `PATCH /api/v1/candidate/skills/{candidateSkill}` — update proficiency, state, years experience
- `POST /api/v1/candidate/skills/{candidateSkill}/archive` — archive a skill
- `POST /api/v1/candidate/skills/{candidateSkill}/restore` — restore an archived skill
- `DELETE /api/v1/candidate/skills/{candidateSkill}` — remove a skill (only when safe)
- `POST /api/v1/candidate/skills/{candidateSkill}/evidence` — add evidence (profile-item reference or URL)
- `PATCH /api/v1/candidate/skills/{candidateSkill}/evidence/{evidenceKey}` — update evidence
- `DELETE /api/v1/candidate/skills/{candidateSkill}/evidence/{evidenceKey}` — remove evidence

### Security
- Policy-based ownership enforcement on every candidate skill and evidence operation
- Cross-user ID access returns 404 (consistent with existing pattern)
- Reject overposting of candidate_profile_id, user_id, or privileged fields
- URL evidence validated against unsafe schemes (javascript:, data:, file:, vbscript:)
- No automatic URL fetching — URLs are stored but not crawled in this change
- Evidence must reference existing owned profile_items — cross-user profile-item references rejected
- Mass-assignment protection on all fillable model attributes
- Rate limiting on search endpoints (60/minute) and mutation endpoints (30/minute)

### Documentation
- OpenAPI 3.1 contract updated with all new endpoints, request/response schemas, error codes
- Skill-state meanings, transition matrix, verification rules documented
- Evidence structure, allowed types, and URL validation documented
- Profile completion explicitly unchanged

### Risks
- **MLD contradiction**: The canonical MLD `candidate_skills` table lacks a `state` column. This change adds it. The MLD must be updated.
- **Evidence storage**: Evidence is stored as JSON within candidate_skills (per MLD). If complex querying of individual evidence items becomes necessary, normalization will be required later.
- **Scope creep into AI/skill extraction**: Explicitly excluded and enforced through review.
- **Duplicate handling**: The UNIQUE(candidate_profile_id, skill_id) constraint prevents duplicates but state transitions must handle existing entries.
- **Alias ambiguity**: Ambiguous aliases (e.g., "JS" could be JavaScript or Java) require candidate selection rather than silent resolution.
