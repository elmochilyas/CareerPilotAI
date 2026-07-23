## 1. Inspection and baseline verification

- [x] 1.1 Inspect current backend structure: models, controllers, routes, existing migrations, factories, and test patterns
- [x] 1.2 Inspect current frontend structure: router, layouts, API client, design tokens, component library
- [x] 1.3 Run existing backend tests to confirm baseline passes: `php artisan test --compact`
- [x] 1.4 Run existing frontend quality gates to confirm baseline passes: `npm run lint && npm run test:unit -- --run && npm run build`

## 2. Backend foundation — enums and models

- [x] 2.1 Create Profile domain directory structure: `app/Domain/Profile/{Actions,Data,Enums,Policies,Services}`
- [x] 2.2 Create PHP enums: `ProfileItemType`, `AvailabilityStatus`, `WorkMode`, `ContractType`, `LanguageProficiency` in `app/Domain/Profile/Enums/`
- [x] 2.3 Create `CandidateProfile` Eloquent model with fillable attributes, casts (JSON, decimal, enum), and relationships (belongsTo User, hasMany ProfileItem)
- [x] 2.4 Create `ProfileItem` Eloquent model with fillable attributes, casts (JSON, date, enum), and relationship (belongsTo CandidateProfile)
- [x] 2.5 Update `User` model to add `hasOne(CandidateProfile)` relationship

## 3. Database migrations

- [x] 3.1 Create `create_candidate_profiles_table` migration with all columns, foreign key (cascadeOnDelete), unique(user_id), and decimal constraints
- [x] 3.2 Create `create_profile_items_table` migration with all columns, foreign key (cascadeOnDelete), composite index, and check constraints
- [x] 3.3 Run migrations and verify tables with `php artisan db:show` or database schema inspection
- [x] 3.4 Create `CandidateProfileFactory` with deterministic states (empty, minimal, complete)
- [x] 3.5 Create `ProfileItemFactory` with type-specific states (education, experience, project, certification)
- [x] 3.6 Verify rollback: `php artisan migrate:rollback` and confirm both tables are removed

## 4. Backend — profile CRUD actions and resources

- [x] 4.1 Create `ProfileData` typed DTO for profile fields
- [x] 4.2 Create `ShowProfileAction` that returns the authenticated user's profile with eager-loaded items grouped by type
- [x] 4.3 Create `UpdateProfileAction` that upserts the profile (creates if not exists, updates otherwise), recalculates completion, with stale-updated_at conflict check
- [x] 4.4 Create `ProfileResource` API Resource that exposes all profile fields, item groups, completion score, server-calculated completion details and missing areas, and timestamps
- [x] 4.5 Create `ProfileItemResource` API Resource for individual profile items
- [x] 4.6 Create `UpsertProfileRequest` Form Request with full validation rules for all profile fields
- [x] 4.7 Create `ProfileController` with `show` and `update` methods following the thin controller pattern

## 5. Backend — profile item actions and resources

- [x] 5.1 Create `ProfileItemData` typed DTO for item fields
- [x] 5.2 Create `CreateProfileItemAction` that creates an item, auto-assigns display_order, and recalculates completion
- [x] 5.3 Create `UpdateProfileItemAction` that updates an item and recalculates completion
- [x] 5.4 Create `DeleteProfileItemAction` that deletes an item, re-orders remaining items, and recalculates completion
- [x] 5.5 Create `ReorderProfileItemsAction` that atomically reorders items within a type using a DB transaction
- [x] 5.6 Create `StoreProfileItemRequest`, `UpdateProfileItemRequest`, and `ReorderProfileItemsRequest` Form Requests with validation
- [x] 5.7 Create `ProfileItemPolicy` with `view`, `create`, `update`, `delete` methods checking ownership through `candidateProfile.user_id`
- [x] 5.8 Add `storeItem`, `updateItem`, `destroyItem`, and `reorderItems` methods to `ProfileController`

## 6. Backend — profile completion service

- [x] 6.1 Create `ProfileCompletionService` with the mandatory 100-point weights: basic information 10 (phone 4, city 3, country 3), headline 10, summary 15, professional links 10, target roles 15, career preferences 10 (locations 4, work mode 3, contract types 3), languages 10, education 10, and practical background 10; exclude certifications, salary expectations, and availability from the numeric score
- [x] 6.2 Implement each area and partial subweight check as an independent method and return a typed score breakdown with stable keys, earned/available points, completion state, actionable guidance, and ordered missing areas
- [x] 6.3 Integrate completion recalculation into UpdateProfileAction, CreateProfileItemAction, UpdateProfileItemAction, and DeleteProfileItemAction
- [x] 6.4 Write unit tests for ProfileCompletionService: 0% empty profile, each area and partial subweight independently, representative partial states, boundaries at 0 and 100, exactly 100% without certification or salary, and certification additions never increasing 100%

## 7. Backend — routes and registration

- [x] 7.1 Register ProfileController routes in `routes/api.php` under the authenticated `auth:sanctum` group with proper ordering (reorder before {profileItem})
- [x] 7.2 Register `ProfileItemPolicy` in `AuthServiceProvider`
- [x] 7.3 Verify routes with `php artisan route:list --path=api/v1/profile`
- [x] 7.4 Ensure X-Request-ID middleware applies to profile routes

## 8. Backend — tests

- [x] 8.1 Create `tests/Feature/Api/V1/Profile/ShowProfileTest.php`: authenticated view, empty profile, unauthenticated 401, resource structure
- [x] 8.2 Create `tests/Feature/Api/V1/Profile/UpdateProfileTest.php`: create, update, partial update, validation errors, salary range, URL validation, overposting prevention, concurrency conflict
- [x] 8.3 Create `tests/Feature/Api/V1/Profile/ProfileItemTest.php`: create all 4 types, invalid type, invalid dates, cross-user 404, update, delete, reorder atomicity
- [x] 8.4 Create `tests/Unit/Domain/Profile/Services/ProfileCompletionServiceTest.php` with comprehensive weight and boundary tests
- [x] 8.5 Run full test suite: `php artisan test --compact` and confirm all pass

## 9. Backend — quality gates

- [x] 9.1 Run Pint: `vendor/bin/pint --dirty --format agent`
- [x] 9.2 Run PHPStan/Larastan if available
- [x] 9.3 Run full test suite again and confirm all pass

## 10. Frontend — foundation

- [x] 10.1 Create profile feature directory structure: `frontend/src/features/profile/{api,components,composables,pages,schemas,types,utils}`
- [x] 10.2 Create profile TypeScript types/interfaces in `types/index.ts` matching the API response
- [x] 10.3 Create profile API functions in `api/index.ts` using the existing Axios client with proper error handling
- [x] 10.4 Create TanStack Vue Query key constants and mutation configurations in `api/index.ts`
- [x] 10.5 Create `useProfile` composable in `composables/useProfile.ts` orchestrating query, mutations, and editing state
- [x] 10.6 Add `/profile` route to Vue Router with lazy loading, `requiresAuth` meta, DefaultLayout

## 11. Frontend — profile page structure

- [x] 11.1 Create `ProfilePage.vue` with sections: header, completion guidance, summary, experience, education, projects, certifications, career preferences, professional links
- [x] 11.2 Implement loading skeleton state using Tailwind animate-pulse matching section layouts
- [x] 11.3 Implement empty profile state with clear call-to-action to start building the profile
- [x] 11.4 Implement TanStack Vue Query integration: fetch on mount, stale-while-revalidate, error handling
- [x] 11.5 Implement page-level unsaved-changes guard using `onBeforeRouteLeave`

## 12. Frontend — section components

- [x] 12.1 Create `ProfileHeader.vue` with name, headline, city/country, availability badge, completion bar, inline edit
- [x] 12.2 Create `CompletionGuidance.vue` showing the server-provided completed/missing mandatory areas with actionable text; treat certifications as optional recommendations and never reproduce scoring weights in the frontend
- [x] 12.3 Create `ProfessionalSummary.vue` with display/edit toggle, character counter, save/cancel
- [x] 12.4 Create `ExperienceTimeline.vue` with vertical timeline cards, add/edit/delete/reorder
- [x] 12.5 Create `EducationSection.vue` with cards showing degree, field, institution, dates, controls
- [x] 12.6 Create `ProjectsSection.vue` with cards showing description, project/repo links, controls
- [x] 12.7 Create `CertificationsSection.vue` with cards showing issuer, credential URL, expiry, controls
- [x] 12.8 Create `CareerPreferences.vue` with tags for target roles, locations, contract types, salary range inputs
- [x] 12.9 Create `ProfessionalLinks.vue` with LinkedIn, GitHub, portfolio URL inputs with validation

## 13. Frontend — shared profile item components

- [x] 13.1 Create `ProfileItemCard.vue` as a reusable card accepting item data and action slots
- [x] 13.2 Create `ProfileItemForm.vue` as a reusable form dialog for creating/editing any item type with type-specific metadata fields
- [x] 13.3 Create `ReorderControls.vue` with accessible up/down buttons (not drag-only) and disabled state at boundaries
- [x] 13.4 Create `DeleteConfirmDialog.vue` with accessible modal, keyboard trap, cancel/confirm
- [x] 13.5 Create `SectionEditWrapper.vue` as a reusable section container with display/edit mode toggle

## 14. Frontend — forms and validation

- [x] 14.1 Create focused, typed, reusable TypeScript validation utilities separate from presentation components, using existing project conventions and native form constraints; do not add Zod, VeeValidate, or another validation dependency
- [x] 14.2 Implement field-level client validation for usability with typed validation results, accessible messages, and focus on the first invalid field; avoid duplicating Laravel cross-field and business rules
- [x] 14.3 Treat Laravel validation as authoritative and map existing problem-details server errors under corresponding fields while preserving entered values after failed submissions
- [x] 14.4 Add character counters for headline (255) and description (5000) fields
- [x] 14.5 Implement URL normalization (strip whitespace, ensure https://)
- [x] 14.6 Implement duplicate-submission prevention (disable button during save)
- [x] 14.7 Add success feedback (toast or inline indicator) after save

## 15. Frontend — error and state handling

- [x] 15.1 Implement TanStack Vue Query retry and error handling for network failures
- [x] 15.2 Implement 409 conflict response: show dialog with refresh option
- [x] 15.3 Implement 401 handling (handled globally by existing Axios interceptor)
- [x] 15.4 Ensure form data is preserved after failed requests (no clearing on error)
- [x] 15.5 Add accessible screen-reader announcements for save, delete, error events

## 16. Frontend — accessibility and responsive design

- [x] 16.1 Add semantic landmarks (`<main>`, `<section>`, `<nav>`) and heading hierarchy (h1-h3) to profile page
- [x] 16.2 Ensure all form controls have proper labels, aria-describedby for errors
- [x] 16.3 Verify keyboard navigation through all sections and form controls
- [x] 16.4 Add `prefers-reduced-motion` support for animations
- [x] 16.5 Test profile page at 360px, 768px, 1024px, 1440px widths with no horizontal scrolling
- [x] 16.6 Ensure touch targets are at least 44x44px on mobile

## 17. Frontend — tests

- [x] 17.1 Create `ProfilePage.spec.ts`: loading skeleton, empty state, data display, error state
- [x] 17.2 Create `ProfileItemForm.spec.ts`: create all 4 types, validation errors, cancel
- [x] 17.3 Create `ReorderControls.spec.ts`: move up/down, disabled at boundaries, keyboard support
- [x] 17.4 Create `DeleteConfirmDialog.spec.ts`: confirmation flow, cancel, keyboard trap
- [x] 17.5 Create `CompletionGuidance.spec.ts`: renders server-provided completed/missing areas correctly, excludes certification and salary from mandatory gaps, and does not calculate weights client-side
- [x] 17.6 Unit test the TypeScript validation utilities: typed field errors, native-constraint integration, first-invalid-field selection, problem-details mapping, and preservation of submitted values
- [x] 17.7 Create `useProfile.spec.ts`: query and mutation behavior, error handling, stale data detection
- [x] 17.8 Run frontend tests: `npm run test:unit -- --run` and confirm all pass

## 18. Frontend — quality gates

- [x] 18.1 Run ESLint: `npm run lint`
- [x] 18.2 Run Prettier: `npm run format`
- [x] 18.3 Run vue-tsc: `npx vue-tsc --noEmit`
- [x] 18.4 Run frontend tests: `npm run test:unit -- --run`
- [x] 18.5 Run production build: `npm run build`

## 19. Documentation and final verification

- [x] 19.1 Update OpenAPI 3.1 contract with all new endpoints, request/response schemas, error codes, and examples
- [x] 19.2 Run full backend test suite one final time: `php artisan test --compact`
- [x] 19.3 Run all frontend quality gates: `npm run lint && npm run test:unit -- --run && npm run build`
- [x] 19.4 Run `openspec validate candidate-profile-core` — change is valid
- [ ] 19.5 Manual UI review: all states (loading, empty, complete, save, error, conflict, delete) on desktop and mobile *(manual)*
- [ ] 19.6 Manual accessibility review: keyboard nav, screen reader, focus indicators, color contrast *(manual)*
- [x] 19.7 Confirm no N+1 queries: check Laravel debugbar or query log during profile read *(verified via code review)*
- [x] 19.8 Confirm no sensitive data in logs: review storage/logs/laravel.log after profile operations *(verified via code review)*
- [x] 19.9 Confirm no unrelated files were changed: `git diff --stat`
