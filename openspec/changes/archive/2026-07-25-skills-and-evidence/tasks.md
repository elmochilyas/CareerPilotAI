## 1. Inspection and baseline verification

- [x] 1.1 Inspect current backend structure: models, controllers, routes, existing migrations, factories, and test patterns
- [x] 1.2 Inspect current frontend structure: router, layouts, API client, design tokens, component library
- [x] 1.3 Run existing backend tests to confirm baseline passes: `php artisan test --compact`
- [x] 1.4 Run existing frontend quality gates to confirm baseline passes: `npm run lint && npm run test:unit -- --run && npm run build`

## 2. Backend foundation — enums and models

- [x] 2.1 Create Skills domain directory structure with empty subdirectories if not already present: `app/Domain/Skills/{Actions,Data,Enums,Events,Policies,Services}`
- [x] 2.2 Create PHP enums: `SkillState` (claimed, verified, learning, rejected, archived), `ProficiencyLevel` (beginner, elementary, intermediate, advanced, expert), `EvidenceType` (profile_item, url, text) in `app/Domain/Skills/Enums/`
- [x] 2.3 Create `Skill` Eloquent model with fillable attributes, casts, and relationships (hasMany aliases, belongsToMany candidateProfiles through candidateSkills)
- [x] 2.4 Create `CandidateSkill` Eloquent model with fillable attributes, casts (state, proficiency_level, years_experience, evidence JSON), and relationships (belongsTo candidateProfile, belongsTo skill)
- [x] 2.5 Create `SkillAlias` Eloquent model with fillable attributes and relationship (belongsTo skill)
- [x] 2.6 Update `CandidateProfile` model to add `hasMany(CandidateSkill)` relationship

## 3. Database migrations

- [x] 3.1 Create `create_skills_table` migration with columns: id, name (VARCHAR 150), normalized_name (VARCHAR 150 UNIQUE), category (VARCHAR 100 nullable), is_active (BOOLEAN default true), timestamps
- [x] 3.2 Create `create_skill_aliases_table` migration with columns: id, skill_id (FK → skills.id CASCADE), alias (VARCHAR 150 UNIQUE), timestamps; index on skill_id
- [x] 3.3 Create `create_candidate_skills_table` migration with columns: id, candidate_profile_id (FK → candidate_profiles.id CASCADE), skill_id (FK → skills.id RESTRICT, nullable for custom skills), state (VARCHAR 30 default 'claimed'), proficiency_level (VARCHAR 30 NOT NULL), years_experience (DECIMAL 4,1 nullable), last_used_at (DATE nullable), evidence (JSON nullable), timestamps; UNIQUE(candidate_profile_id, skill_id); INDEX(candidate_profile_id, state)
- [x] 3.4 Run migrations and verify tables with database schema inspection
- [x] 3.5 Create `SkillFactory` with deterministic states
- [x] 3.6 Create `CandidateSkillFactory` with type-specific states (claimed, verified, learning, archived)
- [x] 3.7 Create `SkillAliasFactory`
- [x] 3.8 Create skill catalog seeder with ~150-200 seeded skills covering common junior tech roles (programming languages, frameworks, databases, tools, cloud platforms, soft skills) with appropriate aliases
- [x] 3.9 Verify rollback: `php artisan migrate:rollback` and confirm all three tables are removed

## 4. Backend — skill catalog actions and resources

- [x] 4.1 Create `SearchSkillsAction` that queries skills table with LIKE on name/normalized_name, supports category filter, paginated, excludes inactive skills
- [x] 4.2 Create `ShowSkillAction` that returns a single skill with aliases
- [x] 4.3 Create `SkillResource` API Resource exposing id, name, normalized_name, category, is_active, aliases, and timestamps
- [x] 4.4 Create `SkillCollection` API Resource for paginated catalog results
- [x] 4.5 Create `SearchSkillRequest` Form Request with validation: q (string, min:2, max:100, nullable), category (string, nullable), page (integer, min:1)
- [x] 4.6 Create `SkillController` with `index` and `show` methods following the thin controller pattern
- [x] 4.7 Register SkillController routes in `routes/api.php` under auth:sanctum group

## 5. Backend — state transition service

- [x] 5.1 Create `SkillStateTransitionService` with the full state transition matrix validation
- [x] 5.2 Implement all 13 allowed transitions with conditions (claimed↔verified requires evidence, etc.)
- [x] 5.3 Implement all forbidden transition detection returning stable error code `skill_state_transition_invalid`
- [x] 5.4 Implement verified-state evidence requirement check
- [x] 5.5 Write comprehensive unit tests covering every allowed and forbidden transition

## 6. Backend — candidate skill actions and resources

- [x] 6.1 Create `CandidateSkillData` typed DTO for candidate skill fields
- [x] 6.2 Create `ListCandidateSkillsAction` that returns authenticated candidate's skills, filterable by state, paginated
- [x] 6.3 Create `CreateCandidateSkillAction` that validates no duplicate (UNIQUE constraint for canonical, name uniqueness for custom), sets default state to claimed, wraps in DB transaction
- [x] 6.4 Create `ShowCandidateSkillAction` that returns a single candidate skill with evidence array
- [x] 6.5 Create `UpdateCandidateSkillAction` that validates state transition via SkillStateTransitionService, updates proficiency/years_experience/last_used_at, checks updated_at, handles concurrency
- [x] 6.6 Create `ArchiveCandidateSkillAction` that transitions the skill to archived state
- [x] 6.7 Create `RestoreCandidateSkillAction` that transitions archived skill to requested target state (claimed, learning, verified)
- [x] 6.8 Create `DeleteCandidateSkillAction` that only allows deletion when state is claimed or learning; returns `candidate_skill_removal_forbidden` otherwise
- [x] 6.9 Create `CandidateSkillResource` API Resource exposing id, skill (canonical or custom), state, proficiency_level, years_experience, last_used_at, evidence array, verification_at_risk flag, and timestamps
- [x] 6.10 Create `CandidateSkillCollection` for paginated list results
- [x] 6.11 Create Form Requests: `StoreCandidateSkillRequest`, `UpdateCandidateSkillRequest`, `ArchiveCandidateSkillRequest`, `RestoreCandidateSkillRequest`, `DeleteCandidateSkillRequest` with full validation rules
- [x] 6.12 Create `CandidateSkillPolicy` with view, create, update, delete, archive, restore methods checking ownership through `candidateProfile.user_id`
- [x] 6.13 Create `CandidateSkillController` with index, store, show, update, archive, restore, destroy methods

## 7. Backend — evidence actions

- [x] 7.1 Create `AddEvidenceAction` that generates UUID key, validates input, appends to evidence JSON array, updates parent updated_at
- [x] 7.2 Create `UpdateEvidenceAction` that finds evidence by UUID key, merges fields, updates parent updated_at
- [x] 7.3 Create `RemoveEvidenceAction` that filters evidence by UUID key, updates parent updated_at
- [x] 7.4 Implement profile_item evidence validation: verify profile_item exists, belongs to the authenticated candidate, and is an allowed type (experience, project, education, certification)
- [x] 7.5 Implement URL evidence validation: reject javascript:, data:, file:, vbscript: schemes; require HTTPS; validate URL format
- [x] 7.6 Create Form Requests: `StoreEvidenceRequest`, `UpdateEvidenceRequest`, `DeleteEvidenceRequest`
- [x] 7.7 Add storeEvidence, updateEvidence, destroyEvidence methods to `CandidateSkillController`
- [x] 7.8 Implement `verification_at_risk` flag in CandidateSkillResource when state is verified but evidence array is empty
- [x] 7.9 Handle profile_item deletion: evidence entry referencing a deleted profile_item shows profile_item as unavailable but the evidence entry is preserved

## 8. Backend — routes and registration

- [x] 8.1 Register all SkillController and CandidateSkillController routes in `routes/api.php` under the authenticated `auth:sanctum` group with proper ordering (evidence routes after {candidateSkill})

```
Route::get('/skills', [SkillController::class, 'index']);
Route::get('/skills/{skill}', [SkillController::class, 'show']);
Route::get('/candidate/skills', [CandidateSkillController::class, 'index']);
Route::post('/candidate/skills', [CandidateSkillController::class, 'store']);
Route::get('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'show']);
Route::patch('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'update']);
Route::post('/candidate/skills/{candidateSkill}/archive', [CandidateSkillController::class, 'archive']);
Route::post('/candidate/skills/{candidateSkill}/restore', [CandidateSkillController::class, 'restore']);
Route::delete('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'destroy']);
Route::post('/candidate/skills/{candidateSkill}/evidence', [CandidateSkillController::class, 'storeEvidence']);
Route::patch('/candidate/skills/{candidateSkill}/evidence/{evidenceKey}', [CandidateSkillController::class, 'updateEvidence']);
Route::delete('/candidate/skills/{candidateSkill}/evidence/{evidenceKey}', [CandidateSkillController::class, 'destroyEvidence']);
```

- [x] 8.2 Register `CandidateSkillPolicy` in `AuthServiceProvider`
- [x] 8.3 Verify routes with `php artisan route:list --path=api/v1/skills` and `--path=api/v1/candidate`
- [x] 8.4 Ensure X-Request-ID middleware applies to all new routes

## 9. Backend — tests

- [x] 9.1 Create `tests/Feature/Api/V1/Skills/SkillCatalogTest.php`
- [x] 9.2 Create `tests/Feature/Api/V1/Skills/CandidateSkillTest.php`
- [x] 9.3 Create `tests/Feature/Api/V1/Skills/StateTransitionTest.php`
- [x] 9.4 Create `tests/Feature/Api/V1/Skills/EvidenceTest.php`
- [x] 9.5 Create `tests/Feature/Api/V1/Skills/ArchiveRestoreTest.php`
- [x] 9.6 Create `tests/Feature/Api/V1/Skills/ConcurrencyTest.php`
- [x] 9.7 Create `tests/Feature/Api/V1/Skills/AuthorizationTest.php`
- [x] 9.8 Create `tests/Unit/Domain/Skills/Services/SkillStateTransitionServiceTest.php`
- [x] 9.9 Create `tests/Feature/Api/V1/Skills/SearchSkillsActionTest.php`
- [x] 9.10 Write a feature test confirming profile completion unchanged
- [x] 9.11 Run full test suite and confirm all pass

## 10. Backend — quality gates

- [x] 10.1 Run Pint: `vendor/bin/pint --dirty --format agent`
- [x] 10.2 Run PHPStan/Larastan if available
- [x] 10.3 Run full test suite again and confirm all pass

## 11. Frontend — foundation

- [x] 11.1 Create skills feature directory structure: `frontend/src/features/skills/{api,components,composables,types}`
- [x] 11.2 Create skill TypeScript types/interfaces in `types/index.ts` matching the API response (Skill, CandidateSkill, SkillEvidence, SkillState, ProficiencyLevel, EvidenceType)
- [x] 11.3 Create skill API functions in `api/index.ts` using existing Axios client with proper error handling for all skill and evidence endpoints
- [x] 11.4 Create TanStack Vue Query key constants and mutation configurations in `api/index.ts` using stable scoped query keys: ['candidate-skills'], ['skills-catalog'], ['skills-catalog', searchTerm]
- [x] 11.5 Create `useSkills` composable in `composables/useSkills.ts` orchestrating queries, mutations, and local UI state

## 12. Frontend — skill section components

- [x] 12.1 Create `SkillsSection.vue` as a new section within the profile page, displaying after existing sections, with loading skeleton, empty state, skill cards grid, and error state
- [x] 12.2 Create `SkillCard.vue` displaying canonical skill name (with "custom" indicator when applicable), state chip with icon/label/shape, proficiency label, evidence count, years experience, last used date, and action buttons (edit, archive/restore, delete, add evidence)
- [x] 12.3 Create `SkillSearchCombobox.vue` following ARIA combobox pattern with debounced search (300ms), keyboard navigation (arrow keys, Enter, Escape), loading/no-result/error states, result count announcement
- [x] 12.4 Create `AddSkillFlow.vue` with multi-step progressive form: search → select → state → proficiency → optional evidence → review → save
- [x] 12.5 Create `StateChip.vue` with differentiated visual treatment for each state using icons, labels, and shapes (not color alone): claimed (outline/info), verified (filled/check), learning (dashed/book), rejected (muted/x), archived (muted/archive)
- [x] 12.6 Create `ProficiencySelect.vue` displaying 5 proficiency levels with candidate-facing labels
- [x] 12.7 Create `EvidenceList.vue` showing evidence entries per skill with type indicator, preview, and remove button
- [x] 12.8 Create `EvidenceSelector.vue` dialog showing candidate's profile items grouped by type for selection, plus URL evidence input with inline validation
- [x] 12.9 Create `EvidenceUrlInput.vue` with URL validation, unsafe-scheme detection, and optional label field

## 13. Frontend — profile page integration

- [x] 13.1 Integrate `SkillsSection.vue` into `ProfilePage.vue` after the existing profile sections
- [x] 13.2 Implement TanStack Vue Query integration: fetch candidate skills on profile mount via `useQuery(['candidate-skills'])`
- [x] 13.3 Ensure skills loading does not block other profile sections (skills load independently)
- [x] 13.4 Handle 401 (global interceptor already handles), 403/404 (show not-found state), 409 (show conflict dialog with refresh), 422 (field-level validation mapping), network failure (retry button)

## 14. Frontend — forms and validation

- [x] 14.1 Implement add-skill form validation: state required, proficiency required, skill_id or custom name required
- [x] 14.2 Implement duplicate detection before submission (check existing skills list client-side, confirm with server)
- [x] 14.3 Implement URL evidence inline validation for unsafe schemes
- [x] 14.4 Implement duplicate-submission prevention (disable buttons during save)
- [x] 14.5 Preserve entered values after validation errors (no form clearing)
- [x] 14.6 Add success feedback (toast or inline indicator) after skill create/update/delete/archive/restore
- [x] 14.7 Implement stale search response cancellation and ignoring

## 15. Frontend — evidence selection UI

- [x] 15.1 Implement profile-item evidence picker dialog showing owned items grouped by type (experience, project, education, certification) with title, organization, dates
- [x] 15.2 Implement URL evidence input with safe-rendering preview
- [x] 15.3 Implement evidence removal with confirmation
- [x] 15.4 Handle evidence mutation success and error states

## 16. Frontend — error and state handling

- [x] 16.1 Implement TanStack Vue Query retry and error handling for network failures
- [x] 16.2 Implement 409 conflict response: show inline warning with refresh button that invalidates the query; do NOT use page reload
- [x] 16.3 Implement 401 handling (handled globally by existing Axios interceptor)
- [x] 16.4 Ensure form data is preserved after failed requests (no clearing on error)
- [x] 16.5 Add accessible screen-reader announcements for save, delete, error events

## 17. Frontend — accessibility and responsive design

- [x] 17.1 Add semantic landmarks and heading hierarchy (h2 for Skills section, h3 for skill cards)
- [x] 17.2 Implement ARIA combobox pattern for skill search with role="combobox", aria-expanded, aria-activedescendant
- [x] 17.3 Ensure all form controls have proper labels, aria-describedby for errors
- [x] 17.4 Implement focus trapping in evidence dialog
- [x] 17.5 Implement focus restoration on close (return focus to trigger element)
- [x] 17.6 Verify keyboard navigation through all skill sections and form controls
- [x] 17.7 Add `prefers-reduced-motion` support for animations
- [x] 17.8 Test skills section at 360px, 768px, 1024px, 1440px widths with no horizontal scrolling
- [x] 17.9 Ensure touch targets are at least 44x44px on mobile
- [x] 17.10 Add aria-live polite region for save confirmations and aria-live assertive for errors

## 18. Frontend — tests

- [x] 18.1 Create `SkillSearchCombobox.spec.ts`: search with debounce, keyboard navigation (arrow keys, Enter, Escape), loading state, no-result state, error state, result count announcement
- [x] 18.2 Create `AddSkillFlow.spec.ts`: select from catalog, custom skill creation, duplicate detection, state selection, proficiency selection, validation errors, cancel
- [x] 18.3 Create `SkillCard.spec.ts`: all 5 states display correctly, proficiency label, evidence count, action buttons, archive/restore visibility
- [x] 18.4 Create `EvidenceSelector.spec.ts`: profile-item picker shows grouped items, URL input validates, rejection of unsafe URLs
- [x] 18.5 Create `SkillsSection.spec.ts`: loading skeleton, empty state, data display, error state with retry
- [x] 18.6 Create `useSkills.spec.ts`: query and mutation behavior, invalidation patterns, error handling, stale data detection
- [x] 18.7 Run frontend tests: `npm run test:unit -- --run` and confirm all pass

## 19. Frontend — quality gates

- [x] 19.1 Run ESLint: `npm run lint`
- [x] 19.2 Run Prettier: `npm run format`
- [x] 19.3 Run vue-tsc: `npx vue-tsc --noEmit`
- [x] 19.4 Run frontend tests: `npm run test:unit -- --run`
- [x] 19.5 Run production build: `npm run build`

## 20. Documentation and final verification

- [ ] 20.1 Update OpenAPI 3.1 contract with all new skill and candidate-skill endpoints, request/response schemas, error codes, and examples
- [x] 20.2 Run full backend test suite one final time: `php artisan test --compact`
- [x] 20.3 Run all frontend quality gates: `npm run lint && npm run test:unit -- --run && npm run build`
- [ ] 20.4 Run `openspec validate skills-and-evidence` — change is valid
- [ ] 20.5 Manual UI review: all states (loading, empty, data, save, error, conflict, delete, archive, restore) on desktop and mobile
- [ ] 20.6 Manual accessibility review: keyboard nav, screen reader, focus indicators, color contrast
- [ ] 20.7 Confirm no N+1 queries: check Laravel debugbar or query log during skill read
- [ ] 20.8 Confirm no sensitive data in logs: review storage/logs/laravel.log after skill operations
- [ ] 20.9 Confirm profile completion unchanged: run profile read before and after skill operations, verify completion value unchanged
- [ ] 20.10 Confirm no unrelated files were changed: `git diff --stat`
