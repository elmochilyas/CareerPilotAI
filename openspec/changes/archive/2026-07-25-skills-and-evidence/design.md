## Context

This change adds skills management to CareerPilot. The existing system has authentication (Sanctum SPA sessions), a candidate profile with typed items (education, experience, project, certification), and a deterministic profile-completion calculator. The Skills domain directory is scaffolded but empty. The canonical MLD defines `skills` and `candidate_skills` tables but lacks a `state` column — this is a documented contradiction resolved by this change.

Relevant existing files:
- `docs/database/MLD.md` — skills and candidate_skills table definitions (needs state column addition)
- `docs/database/MCD.md` — MAITRISER association between CANDIDATE_PROFILE and SKILL
- `docs/database/IMPLEMENTATION_PLAN.md` — Phase 1 includes skills-and-evidence
- `openspec/config.yaml` — SKILL-001 through SKILL-006 requirements, skill states, match factors
- `backend/app/Domain/Skills/` — scaffolded but empty Actions, Data, Enums, Events, Policies, Services directories
- `backend/app/Models/CandidateProfile.php` — hasMany skills relationship to add
- `backend/app/Models/ProfileItem.php` — evidence references this model
- `backend/app/Domain/Profile/Enums/ProfileItemType.php` — Education, Experience, Project, Certification
- `backend/app/Domain/Profile/Services/ProfileCompletionService.php` — must remain unchanged
- `frontend/src/features/profile/` — existing profile feature where skills section will be added
- `docs/api/openapi.yaml` — to extend with skill endpoints

Key contradictions discovered:
1. MLD `candidate_skills` has no `state` column — config.yaml requires claimed/verified/learning/rejected/archived states
2. MLD evidence is stored as JSON in `candidate_skills.evidence` — no separate `skill_evidence` table despite config.yaml listing it as "planned"
3. MLD has no `skill_aliases` table despite SKILL-006 requiring alias resolution
4. Config.yaml lists `skill_aliases` in planned tables but also lists `skill_evidence` as a planned table while MLD merged it into JSON

Resolution: Add `state` column to `candidate_skills`, store evidence as JSON per MLD, add `skill_aliases` as a separate table (not JSON) to support deterministic alias resolution with UNIQUE constraints.

## Goals / Non-Goals

**Goals:**
- Create `skills`, `candidate_skills`, `skill_aliases` database tables
- Seed a canonical skill catalog with normalized names
- Build CRUD API for candidate skills with state transitions, proficiency, and evidence
- Implement deterministic state-transition enforcement with auditable rules
- Implement alias normalization (safe aliases auto-resolve, ambiguous aliases require selection)
- Build evidence management within candidate_skills (profile-item references and URL evidence as JSON)
- Build Vue 3 skills section integrated into the existing profile page
- Enforce ownership isolation, validation, duplicate prevention, and concurrency controls
- Keep profile completion weights unchanged

**Non-Goals:**
- CV upload or AI skill extraction
- Admin skill-catalog management interface (seeded catalog only)
- Automatic skill verification
- Repository crawling or analysis
- Matching engine changes (match factors are defined but not implemented)
- Profile completion recalculation involving skills
- Full profile-page redesign
- Any AI dependency
- External URL fetching or crawling
- Notification system changes
- Evidence strength scoring

## Decisions

### Decision 1: Add state column to candidate_skills
The canonical MLD lacks a `state` column on `candidate_skills`. The config.yaml defines five required states (claimed, verified, learning, rejected, archived). This change adds `state VARCHAR(30) NOT NULL DEFAULT 'claimed'` with a CHECK constraint. The MLD will be updated in a separate docs change after this is approved.

**Rejected alternative**: Storing state in a separate table would over-complicate simple state transitions.

### Decision 2: Store evidence as JSON in candidate_skills
Per the MLD, evidence is a JSON column on `candidate_skills`. This aligns with the pattern of storing bounded, non-queryable flexible data as JSON. Each evidence entry has a UUID key, type discriminator, and type-specific payload (profile_item_id or URL with metadata). Individual evidence items are added/removed by UUID key.

**Rejected alternative**: Separate `skill_evidence` table would add schema complexity. JSON storage is sufficient until complex cross-candidate evidence querying is needed.

### Decision 3: Separate skill_aliases table (not JSON aliases within skills)
Aliases need UNIQUE constraints to prevent duplicates and enable deterministic lookups. A separate `skill_aliases` table with UNIQUE(alias) provides referential integrity. JSON aliases within the skills table would require application-level uniqueness checks that are less reliable.

### Decision 4: Verified requires at least one valid evidence item
A skill cannot transition from any state to `verified` without at least one evidence item. The evidence must be valid (existing profile_item owned by the candidate, or a safe URL). This is enforced in the `TransitionSkillStateAction` service. Verification is candidate-attested with evidence, not third-party verified.

**Rejected alternative**: Allowing verified without evidence would violate the truthfulness principle.

### Decision 5: Skill removal allowed only for claimed and learning states
Skills in `verified` or `rejected` states cannot be removed — they can only be `archived`. This preserves audit history for skills that have matching or application relevance. `archived` skills can be restored.

### Decision 6: Custom skills allowed only when the canonical catalog does not contain the skill
A candidate can add a custom skill (proficiency_level only, no normalized_name) when the skill does not exist in the canonical catalog. Custom skills are prefixed with `custom:` in the name and have no skill_id FK during creation. A follow-up migration or later change can normalize custom skills into canonical ones.

**Rejected alternative**: Restricting to catalog-only skills would block candidates with niche or emerging technologies.

### Decision 7: Concurrency via updated_at on candidate_skills
Same pattern as profile-core: use `updated_at` as the concurrency token. Evidence mutations update the parent candidate_skill's `updated_at`. The client sends last-known `updated_at`; server rejects with 409 on mismatch.

### Decision 8: Skills section within profile page, not separate route
The skills section is added as a new section within the existing `ProfilePage.vue` component. This keeps the profile as the single source of candidate data and avoids unnecessary navigation. The section collapses/expands like other profile sections.

### Decision 9: 404 for cross-user access (consistent with profile-core)
Return 404 (not 403) when a user attempts to access another user's candidate skill or evidence. Per OWASP guidelines, 404 reveals no information about resource existence.

### Decision 10: Cascade delete on candidate_profile delete for candidate_skills
When a candidate_profile is deleted (via user deletion cascade), all associated candidate_skills are cascade-deleted. Evidence stored as JSON is removed with the row. skill_id FK uses RESTRICT — a canonical skill cannot be deleted while candidates reference it.

## State Transition Matrix

| From → To | Allowed | Conditions |
|---|---|---|
| claimed → verified | Yes | Must have at least one valid evidence item |
| claimed → learning | Yes | No conditions |
| claimed → archived | Yes | No conditions |
| claimed → rejected | Yes | No conditions |
| verified → claimed | Yes | Explicit candidate confirmation (downgrade) |
| verified → learning | Yes | Explicit candidate confirmation; evidence preserved |
| verified → archived | Yes | No conditions |
| verified → rejected | Yes | Explicit candidate confirmation |
| learning → claimed | Yes | No conditions |
| learning → verified | Yes | Must have at least one valid evidence item |
| learning → archived | Yes | No conditions |
| learning → rejected | Yes | No conditions |
| rejected → claimed | Yes | No conditions |
| rejected → learning | Yes | No conditions |
| rejected → archived | Yes | No conditions |
| archived → claimed | Yes | Restoration |
| archived → learning | Yes | Restoration |
| archived → verified | Yes | Restoration + must have at least one valid evidence item |
| archived → rejected | Yes | No conditions |

Forbidden transitions (return 422 with code `skill_state_transition_invalid`):
- verified → verified (no-op, returns current resource)
- claimed → claimed (no-op)
- learning → learning (no-op)
- rejected → rejected (no-op)
- archived → archived (no-op)

Transition to `verified` from any state requires at least one valid evidence entry in the evidence JSON. Evidence validity is checked at transition time, not at evidence-add time.

## Risks / Trade-offs

- **MLD contradiction**: MLD lacks state column; resolved by adding it in migration. MLD doc must be updated after approval.
- **Evidence as JSON**: Cannot query individual evidence items with SQL. Mitigation: evidence keyed by UUID for targeted updates/deletes. Normalization can follow if query patterns emerge.
- **Custom skills without normalization**: Custom skills have no skill_id FK and no normalized_name. They are not searchable in the catalog. Mitigation: console/command to migrate custom skills to canonical when new skills are added to catalog.
- **No admin catalog UI**: Seeded catalog may lack skills candidates need. Mitigation: custom skill creation bypasses this limitation.
- **Scope creep**: Risk of expanding into matching or resume features. Mitigation: explicit non-goals and review gate.
- **Duplicate prevention**: UNIQUE(candidate_profile_id, skill_id) constraint prevents duplicates for canonical skills. Custom skills (null skill_id) use application-level uniqueness check.
- **Evidence deletion impact**: Removing the only evidence from a verified skill does NOT automatically change state. The mismatch is flagged in the API response but the candidate must explicitly change state. Mitigation: API returns `verification_at_risk` flag when verified skill has no evidence.

## Migration Plan

1. Create `skills` table
2. Create `skill_aliases` table
3. Create `candidate_skills` table
4. Run seeders for initial canonical skills (top 100-200 tech skills relevant to junior candidates)
5. Rollback: drop candidate_skills, skill_aliases, skills in reverse order

Migration order respects FK dependencies: skills first, then skill_aliases (FK → skills), then candidate_skills (FK → candidate_profiles, skills).

## API Contract

### Skill Catalog Endpoints

#### GET /api/v1/skills
- **Auth:** Required (auth:sanctum)
- **Query params:** `q` (search string, min 2 chars), `category` (filter), `page` (page-based pagination)
- **Response 200:** Paginated list of canonical skills with aliases
- **Response 422:** Invalid search query

#### GET /api/v1/skills/{skill}
- **Auth:** Required
- **Response 200:** Single skill with aliases
- **Response 404:** Skill not found

### Candidate Skill Endpoints

#### GET /api/v1/candidate/skills
- **Auth:** Required
- **Query params:** `state` (filter, optional), `page` (pagination)
- **Response 200:** Paginated list of candidate's skills with evidence summary

#### POST /api/v1/candidate/skills
- **Auth:** Required
- **Request:** `{ skill_id (nullable for custom), name (required when custom), state, proficiency_level, years_experience?, last_used_at? }`
- **Response 201:** Created candidate skill
- **Response 409:** Duplicate skill (code: `candidate_skill_duplicate`)
- **Response 422:** Validation errors
- **Idempotency:** Uses normalized uniqueness check; duplicate returns conflict

#### GET /api/v1/candidate/skills/{candidateSkill}
- **Auth:** Required
- **Response 200:** Full candidate skill with evidence array
- **Response 404:** Not found or not owned

#### PATCH /api/v1/candidate/skills/{candidateSkill}
- **Auth:** Required
- **Request:** `{ state?, proficiency_level?, years_experience?, last_used_at?, updated_at }`
- **Response 200:** Updated candidate skill
- **Response 409:** Conflict on stale updated_at
- **Response 422:** Invalid state transition or validation

#### POST /api/v1/candidate/skills/{candidateSkill}/archive
- **Auth:** Required
- **Request:** `{ updated_at }`
- **Response 200:** Archived skill
- **Response 409:** Conflict
- **Response 422:** Invalid transition

#### POST /api/v1/candidate/skills/{candidateSkill}/restore
- **Auth:** Required
- **Request:** `{ state (target state after restore: claimed, learning, verified), updated_at }`
- **Response 200:** Restored skill
- **Response 409:** Conflict
- **Response 422:** Invalid transition

#### DELETE /api/v1/candidate/skills/{candidateSkill}
- **Auth:** Required
- **Request:** `{ updated_at }`
- **Response 204:** Deleted (only if state is claimed or learning)
- **Response 422:** Cannot delete verified/rejected skill (must archive instead)
- **Response 404:** Not found or not owned
- **Response 409:** Conflict

### Evidence Endpoints

#### POST /api/v1/candidate/skills/{candidateSkill}/evidence
- **Auth:** Required
- **Request:** `{ type (profile_item or url), profile_item_id? (when type=profile_item), url? (when type=url), label? }`
- **Response 201:** Evidence entry added
- **Response 422:** Invalid evidence type, unsafe URL, cross-user profile_item
- **Response 404:** Skill not found or not owned

#### PATCH /api/v1/candidate/skills/{candidateSkill}/evidence/{evidenceKey}
- **Auth:** Required
- **Request:** Partial evidence fields
- **Response 200:** Updated evidence entry
- **Response 404:** Evidence key not found
- **Response 422:** Invalid update

#### DELETE /api/v1/candidate/skills/{candidateSkill}/evidence/{evidenceKey}
- **Auth:** Required
- **Response 204:** Evidence entry removed
- **Response 404:** Evidence key not found
- **Note:** Does not change skill state. If verified skill loses its last evidence, API returns `verification_at_risk` flag.

### Stable Error Codes
| Code | HTTP Status | Description |
|---|---|---|
| `candidate_skill_duplicate` | 409 | Skill already exists in candidate inventory |
| `skill_state_transition_invalid` | 422 | Requested state transition is not allowed |
| `skill_evidence_invalid` | 422 | Evidence type, URL, or reference is invalid |
| `skill_verification_requirements_not_met` | 422 | Cannot transition to verified without evidence |
| `profile_item_evidence_not_owned` | 422 | Referenced profile_item does not belong to candidate |
| `candidate_skill_conflict` | 409 | Stale updated_at value |
| `candidate_skill_not_found` | 404 | Skill not found or not owned by candidate |
| `skill_evidence_not_found` | 404 | Evidence key not found on candidate skill |
| `candidate_skill_removal_forbidden` | 422 | Cannot delete skill in current state |

### Evidence JSON Structure
```json
{
  "evidence": [
    {
      "key": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "type": "profile_item",
      "profile_item_id": 42,
      "label": "Laravel E-commerce Project",
      "added_at": "2026-07-23T12:00:00Z"
    },
    {
      "key": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
      "type": "url",
      "url": "https://github.com/user/project",
      "label": "GitHub Repository",
      "added_at": "2026-07-23T12:00:00Z"
    }
  ]
}
```

### Evidence Types
- `profile_item`: References existing profile_items record owned by candidate. Supported types: experience, project, education, certification.
- `url`: HTTPS URL evidence with validated scheme. Not fetched or crawled.
- `text`: Optional free-text explanation of evidence (supported but not required).

## Skills Domain Organization

```
app/Domain/Skills/
├── Actions/
│   ├── SearchSkillsAction.php
│   ├── ShowSkillAction.php
│   ├── ListCandidateSkillsAction.php
│   ├── CreateCandidateSkillAction.php
│   ├── ShowCandidateSkillAction.php
│   ├── UpdateCandidateSkillAction.php
│   ├── TransitionCandidateSkillStateAction.php
│   ├── ArchiveCandidateSkillAction.php
│   ├── RestoreCandidateSkillAction.php
│   ├── DeleteCandidateSkillAction.php
│   ├── AddEvidenceAction.php
│   ├── UpdateEvidenceAction.php
│   └── RemoveEvidenceAction.php
├── Data/
│   ├── SkillData.php
│   └── CandidateSkillData.php
├── Enums/
│   ├── SkillState.php
│   ├── ProficiencyLevel.php
│   └── EvidenceType.php
├── Events/
│   └── (empty — no event system yet; placeholder for future audit events)
├── Policies/
│   ├── CandidateSkillPolicy.php
│   └── EvidencePolicy.php
└── Services/
    └── SkillStateTransitionService.php
```

### Controllers
- `SkillController` — catalog endpoints (search, show)
- `CandidateSkillController` — candidate skill CRUD, evidence management

### Form Requests
- `SearchSkillRequest` — validates q (min 2 chars), category, page
- `StoreCandidateSkillRequest` — validates skill_id/name, state, proficiency_level, years_experience, last_used_at
- `UpdateCandidateSkillRequest` — validates state transition, proficiency, years_experience, last_used_at, updated_at
- `ArchiveCandidateSkillRequest` — validates updated_at
- `RestoreCandidateSkillRequest` — validates target state, updated_at
- `DeleteCandidateSkillRequest` — validates updated_at
- `StoreEvidenceRequest` — validates type (in:profile_item,url,text), profile_item_id, url, label
- `UpdateEvidenceRequest` — validates partial evidence fields
- `DeleteEvidenceRequest` — validates updated_at (evidence removal updates parent updated_at)

### Routes
```php
Route::middleware(['auth:sanctum', 'throttle:120,1'])->prefix('v1')->group(function () {
    // Skill catalog
    Route::get('/skills', [SkillController::class, 'index']);
    Route::get('/skills/{skill}', [SkillController::class, 'show']);

    // Candidate skills
    Route::get('/candidate/skills', [CandidateSkillController::class, 'index']);
    Route::post('/candidate/skills', [CandidateSkillController::class, 'store']);
    Route::get('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'show']);
    Route::patch('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'update']);
    Route::post('/candidate/skills/{candidateSkill}/archive', [CandidateSkillController::class, 'archive']);
    Route::post('/candidate/skills/{candidateSkill}/restore', [CandidateSkillController::class, 'restore']);
    Route::delete('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'destroy']);

    // Evidence
    Route::post('/candidate/skills/{candidateSkill}/evidence', [CandidateSkillController::class, 'storeEvidence']);
    Route::patch('/candidate/skills/{candidateSkill}/evidence/{evidenceKey}', [CandidateSkillController::class, 'updateEvidence']);
    Route::delete('/candidate/skills/{candidateSkill}/evidence/{evidenceKey}', [CandidateSkillController::class, 'destroyEvidence']);
});
```

### Policy
`CandidateSkillPolicy` — view, create, update, delete, archive, restore methods. All check `$candidateSkill->candidateProfile->user_id === $user->id`.

Evidence operations gate through the parent skill's policy (no separate evidence policy needed since evidence is embedded JSON).

## Backend Architecture

### Actions

**SearchSkillsAction**: Query `skills` table with `LIKE` on name and normalized_name, paginated. Include alias resolution. Return collection with aliases eager-loaded.

**CreateCandidateSkillAction**: Validate no duplicate (check UNIQUE constraint for canonical, name uniqueness for custom). Validate state transition (new skill always starts in valid state). Set default state to `claimed` if not provided. Wrap in DB transaction. Return created skill with evidence.

**TransitionCandidateSkillStateAction** (called from UpdateCandidateSkillAction): Validate the transition against the state matrix. If transitioning to `verified`, check for at least one evidence entry. Update state and return updated skill. This is the core business logic.

### State Transition Service
`SkillStateTransitionService` — centralized validation of state transitions. Accepts current state, requested state, and evidence array. Returns allowed boolean and optional reason. Called by actions, not directly by controllers.

### Evidence Operations
Evidence is stored as JSON in `candidate_skills.evidence`. Operations:
- **Add**: Generate UUID key, validate input, append to evidence array, save.
- **Update**: Find by UUID key, merge fields, save.
- **Remove**: Filter out by UUID key, save.
- **All operations**: Update the parent `updated_at` timestamp.

Evidence mutation does NOT recalculate verification status. The mismatch between verified state and empty evidence is surfaced via a `verification_at_risk` flag in the API response.

## Migrations

### create_skills_table
```php
Schema::create('skills', function (Blueprint $table) {
    $table->id();
    $table->string('name', 150);
    $table->string('normalized_name', 150)->unique();
    $table->string('category', 100)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### create_skill_aliases_table
```php
Schema::create('skill_aliases', function (Blueprint $table) {
    $table->id();
    $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
    $table->string('alias', 150)->unique();
    $table->timestamps();

    $table->index('skill_id');
});
```

### create_candidate_skills_table
```php
Schema::create('candidate_skills', function (Blueprint $table) {
    $table->id();
    $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
    $table->foreignId('skill_id')->nullable()->constrained()->restrictOnDelete();
    $table->string('state', 30)->default('claimed');
    $table->string('proficiency_level', 30);
    $table->decimal('years_experience', 4, 1)->nullable();
    $table->date('last_used_at')->nullable();
    $table->json('evidence')->nullable();
    $table->timestamps();

    $table->unique(['candidate_profile_id', 'skill_id']);
    $table->index(['candidate_profile_id', 'state']);
});
```

### Rollback
Drop candidate_skills, skill_aliases, skills in reverse order. All are pure additions with no data loss risk on rollback.

## Validation Rules

### Skill catalog search
| Field | Rules |
|---|---|
| q | string, min:2, max:100, nullable (returns all when omitted) |
| category | string, max:100, nullable, in:known_categories |
| page | integer, min:1, default:1 |

### Candidate skill fields
| Field | Rules |
|---|---|
| skill_id | integer, exists:skills,id, nullable (null = custom skill) |
| name | required_without:skill_id, string, max:150 (custom skill name) |
| state | string, in:claimed,learning,verified,rejected,archived, default:claimed |
| proficiency_level | string, required, in:beginner,elementary,intermediate,advanced,expert |
| years_experience | numeric, min:0, max:99.9, nullable |
| last_used_at | date, nullable, before_or_equal:today |
| updated_at | string, date-format:Y-m-d\TH:i:s.u\Z, required for concurrency checks |

### Evidence fields
| Field | Rules |
|---|---|
| type | string, required, in:profile_item,url,text |
| profile_item_id | integer, required_if:type,profile_item, exists:profile_items,id |
| url | url, required_if:type,url, max:500, starts_with:https:// |
| label | string, max:255, nullable |
| updated_at | string, date-format, required for evidence mutations |

### Proficiency Level Enum
Stored values: `beginner`, `elementary`, `intermediate`, `advanced`, `expert`

All states accept all proficiency levels. Changing proficiency does not affect verification. Lowering proficiency does not invalidate verification.

### Evidence Type Enum
Stored values: `profile_item`, `url`, `text`

## Skill Alias Resolution

Aliases are stored in the `skill_aliases` table with UNIQUE(alias). Resolution rules:

1. Trim leading/trailing spaces from input.
2. Case-insensitive match against aliases table.
3. Case-insensitive match against skill normalized_name.
4. If exactly one match → auto-resolve to that canonical skill.
5. If multiple matches → return ambiguous results for candidate selection.
6. If no match → offer custom skill creation.

Examples:
- "JS" → resolves to "JavaScript" (unambiguous alias)
- "TS" → resolves to "TypeScript" (unambiguous alias)
- "C" → ambiguous (could be "C" or "C#"? — no, "C# is different enough). But if both "C" and "C++" match — depends on aliases. Realistically, ambiguous resolution is rare for well-designed aliases.)

Safe aliases are seeded alongside the catalog. Aliases are never auto-generated from skill names.

## Frontend Architecture

### Feature structure (new)
```
frontend/src/features/skills/
├── api/
│   └── index.ts
├── components/
│   ├── SkillsSection.vue
│   ├── SkillCard.vue
│   ├── SkillSearchCombobox.vue
│   ├── AddSkillFlow.vue
│   ├── EvidenceList.vue
│   ├── EvidenceSelector.vue
│   ├── EvidenceUrlInput.vue
│   ├── StateChip.vue
│   └── ProficiencySelect.vue
├── composables/
│   └── useSkills.ts
├── types/
│   └── index.ts
```

The Skills section lives within `ProfilePage.vue` (no new route). Integration point: rendered after the existing profile sections (Experience, Education, etc.).

### Data flow
- TanStack Vue Query for all skill server state
- Query keys: `['candidate-skills']`, `['candidate-skills', id]`, `['skills-catalog']`, `['skills-catalog', searchTerm]`
- Mutations with invalidation of `['candidate-skills']` queries
- No Pinia store for skill data
- Composable `useSkills` orchestrates queries, mutations, and local UI state
- Skill search uses debounced query with cancellation of stale requests

### Query and mutation composables
```typescript
// Queries
useCandidateSkills(filters?: { state?: SkillState }) // useQuery(['candidate-skills', filters], ...)
useCandidateSkill(id: number) // useQuery(['candidate-skills', id], ...)
useSkillCatalogSearch(query: string) // useQuery(['skills-catalog', query], ..., { enabled: query.length >= 2 })
useSkill(id: number) // useQuery(['skills-catalog', id], ...)

// Mutations
useCreateCandidateSkill() // useMutation -> invalidate ['candidate-skills']
useUpdateCandidateSkill() // useMutation -> invalidate ['candidate-skills']
useArchiveCandidateSkill() // useMutation -> invalidate ['candidate-skills']
useRestoreCandidateSkill() // useMutation -> invalidate ['candidate-skills']
useDeleteCandidateSkill() // useMutation -> invalidate ['candidate-skills']
useAddEvidence() // useMutation -> invalidate ['candidate-skills', skillId]
useUpdateEvidence() // useMutation -> invalidate ['candidate-skills', skillId]
useRemoveEvidence() // useMutation -> invalidate ['candidate-skills', skillId]
```

### Component states per section
| State | Behavior |
|---|---|
| Loading | Skeleton matching skill card layout |
| Empty | "Add your first skill" prompt with search button |
| Data | Skill cards with state chips, proficiency, evidence count |
| Saving | Disabled controls, saving indicator |
| Validation error | Field-level errors, error summary |
| Server error | Inline error with retry, data preserved |
| Conflict (409) | Warning that data is stale, refresh prompt |

### Skill Card
Each card displays:
- Canonical skill name (or custom name with "custom" indicator)
- State chip with icon, label, and color-supporting shape
- Proficiency label
- Years experience (when provided)
- Evidence count with status indicator (enough for verification?)
- Last used date (when provided)
- Action buttons: edit, archive/restore, delete, add evidence

State differentiation:
- claimed: outline badge, "Claimed" label, info icon
- verified: filled badge, "Verified" label, check icon
- learning: dashed badge, "Learning" label, book icon
- rejected: muted badge, "Rejected" label, x icon
- archived: muted badge, "Archived" label, archive icon

### Add-skill flow
1. Click "Add Skill" → search combobox expands
2. Type to search (debounced 300ms, min 2 chars)
3. Select canonical skill from results, or choose "Add custom skill"
4. Enter proficiency level (required)
5. Select state (default: claimed)
6. Optionally add evidence (profile-item picker or URL input)
7. Review and save
8. Duplicate detection shown inline if attempt is made to add existing skill

### Evidence selection UI
When adding profile-item evidence:
- Modal/dialog shows candidate's profile items grouped by type (experience, project, education, certification)
- Each item shows title, organization, dates
- Select one item as evidence
- Preview the evidence entry before confirming

When adding URL evidence:
- Input field with URL validation
- Label field (optional)
- Inline validation for unsafe schemes

### Accessibility
- Skill search combobox follows ARIA combobox pattern
- State chips use aria-label and role="status"
- Evidence dialog traps focus
- Focus restored after add/edit/delete operations
- aria-live region for save confirmations and errors
- All controls keyboard accessible
- Color is supplementary to text/icons for state differentiation

### Responsive design
- Desktop: grid layout for skill cards (2-3 columns), side panel for add flow
- Tablet: 2-column grid, inline add flow
- Mobile: single column, full-width add flow, no horizontal scroll

## Security and Privacy

### Authorization
- `auth:sanctum` middleware on all skill routes
- `CandidateSkillPolicy` gates every candidate skill operation
- Cross-user requests return 404
- Profile-item evidence references validated for ownership

### Input security
- URL evidence validated against unsafe schemes (javascript:, data:, file:, vbscript:)
- Text fields stored without HTML (no rich text)
- skill_id validated against existing skills table
- profile_item_id validated against owned profile_items only

### Logging
- Log skill operations with request_id and user identifier
- Do not log evidence details or custom skill names
- Validation errors logged without full request body

### Concurrency
- All candidate skill mutations require `updated_at` check
- Evidence mutations update parent skill `updated_at`
- Stale data returns 409 conflict

### Safe external links
- URL evidence rendered with `target="_blank"` and `rel="noopener noreferrer"`
- No automatic URL fetching during this capability

## Testing Strategy

### Backend tests (Pest)
- `tests/Feature/Api/V1/Skills/SkillCatalogTest.php`: search, filter, pagination, show
- `tests/Feature/Api/V1/Skills/CandidateSkillTest.php`: create (claimed, learning), duplicate prevention, custom skill, invalid proficiency, cross-user 404
- `tests/Feature/Api/V1/Skills/StateTransitionTest.php`: all valid transitions, all invalid transitions, verified requires evidence, verify after evidence removal
- `tests/Feature/Api/V1/Skills/EvidenceTest.php`: add profile-item evidence, add URL evidence, unsafe URL rejection, cross-user profile-item rejection, remove evidence
- `tests/Feature/Api/V1/Skills/ArchiveRestoreTest.php`: archive, restore to claimed/learning/verified, invalid restore states
- `tests/Feature/Api/V1/Skills/ConcurrencyTest.php`: stale updated_at returns 409
- `tests/Feature/Api/V1/Skills/AuthorizationTest.php`: cross-user access returns 404 for all operations
- `tests/Unit/Domain/Skills/Services/SkillStateTransitionServiceTest.php`: comprehensive matrix tests
- `tests/Unit/Domain/Skills/Actions/SearchSkillsActionTest.php`: alias resolution, ambiguous handling, edge cases
- Profile completion unchanged: verify completion percentage does not change after skill operations

### Frontend tests (Vitest)
- `SkillSearchCombobox.spec.ts`: search with debounce, keyboard navigation, no-result state, error state
- `AddSkillFlow.spec.ts`: existing skill selection, custom skill, duplicate detection, form validation
- `SkillCard.spec.ts`: all states, chips, evidence count, actions
- `EvidenceSelector.spec.ts`: profile-item picker, URL input, validation
- `SkillsSection.spec.ts`: loading, empty, data states, conflict recovery
- `useSkills.spec.ts`: query/mutation behavior, invalidation, error handling
- Accessibility: keyboard nav, focus management, aria announcements

### Quality gates
- `vendor/bin/pint --dirty --format agent`
- `php artisan test --compact`
- `npm run lint`
- `npm run format`
- `npm run test:unit -- --run`
- `npm run build`
- `npx vue-tsc --noEmit`

## Open Questions

1. **Custom skills with null skill_id**: Should custom skills be visible in the canonical catalog search? Decision: No — they are private to the candidate.
2. **Evidence order**: Does evidence display order matter? Decision: Array order reflects addition order; no explicit reordering.
3. **Seeded catalog size**: How many skills to seed? Decision: ~150-200 skills covering common junior tech roles (web development, data, mobile, cloud, DevOps, soft skills). Expanded over time.
4. **State migration for existing skills**: No existing skills exist. The `state` column defaults to `claimed` for backward compatibility.
