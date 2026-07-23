## Context

This change establishes the authenticated candidate's professional profile — the foundation of every downstream feature. The existing system has authentication (Sanctum SPA sessions, users table) but no profile storage or UI.

Relevant existing files:
- `backend/app/Models/User.php` — hasOne profile relationship to add
- `backend/app/Http/Controllers/Api/V1/AuthController.php` — thin controller pattern
- `backend/app/Domain/Identity/Actions/*` — action delegation pattern
- `backend/routes/api.php` — authenticated route group under `auth:sanctum`
- `docs/database/MCD.md` — CANDIDATE_PROFILE and PROFILE_ITEM entities
- `docs/database/MLD.md` — candidate_profiles and profile_items table definitions
- `docs/database/IMPLEMENTATION_PLAN.md` — Phase 1 covers this change
- `frontend/src/router/index.ts` — lazy route pattern with `requiresAuth` meta
- `frontend/src/components/ui/AppLayout.vue` — shell layout for authenticated pages
- `frontend/src/api/client/axios.ts` — centralized Axios with CSRF and 401 handling
- `frontend/src/app/providers/query-client.ts` — TanStack Vue Query config
- `frontend/src/stores/auth.ts` — Pinia auth store pattern
- `frontend/src/assets/main.css` — Tailwind v4 theme with primary-500 blue palette

## Goals / Non-Goals

**Goals:**
- Create `candidate_profiles` and `profile_items` database tables
- Build CRUD API for profile and typed profile items (education, experience, project, certification)
- Implement deterministic profile-completion calculator
- Build premium-quality Vue 3 profile interface with section-based editing
- Enforce ownership isolation, validation, and concurrency controls
- Reuse existing authentication — no auth redesign

**Non-Goals:**
- Skills, candidate skills, or skill evidence
- CV upload, extraction, or AI profile generation
- File uploads
- Profile version history or change auditing
- Public candidate profiles
- Admin profile management
- Notifications
- Any AI dependency

## Decisions

### Decision 1: PATCH semantics for profile updates
Use PATCH instead of PUT. The profile is a single resource per user with many optional fields. PATCH allows sending only changed fields, which is more natural for a profile that accumulates data over time. The client sends partial objects; null fields are treated as intentional clearing.

Rejected alternative: PUT would require sending every field on every update, forcing the client to track full state.

### Decision 2: Shared profile_items table with type discriminator
Use one `profile_items` table with a `type` column restricted to `education`, `experience`, `project`, `certification`. Metadata JSON holds type-specific fields.

Rationale: The four item types share the same CRUD pattern, ordering, and ownership. Separate tables would duplicate infrastructure. The MCD/MLD already specifies this approach.

Rejected alternative: Separate tables per type (educations, experiences, etc.) — more schema complexity, joins for unified views, no demonstrated query benefit at this stage.

### Decision 3: Section-based inline editing over modal forms
Each profile section (headline, summary, experience timeline, etc.) has its own edit state with explicit save/cancel.

Rationale: Section editing preserves context — the user sees the change in place. Modals hide the surrounding content. Dedicated edit routes create unnecessary navigation. Inline editing is more accessible when each section is a well-labeled region.

### Decision 4: 404 for cross-user item access
Return 404 (not 403) when a user attempts to access another user's profile item.

Rationale: 404 reveals no information about whether the resource exists. 403 confirms the resource exists but access is denied. For private candidate data, 404 is the safer default per OWASP guidelines.

### Decision 5: updated_at optimistic concurrency
Use `updated_at` as the concurrency token on both profile and profile item endpoints.

Rationale: No additional column needed. Laravel's Eloquent already manages `updated_at`. The client sends the last-known `updated_at` in the request body; the server rejects if it does not match. This prevents silent overwrites from stale tabs.

Rejected alternative: Explicit version column — adds schema complexity without clear benefit since `updated_at` already provides sufficient granularity for a single-user writer.

### Decision 6: Cascade delete on user deletion
`candidate_profiles.user_id` foreign key uses CASCADE. `profile_items.candidate_profile_id` also uses CASCADE.

Rationale: A candidate profile has no meaning without its owning user. Profile items have no meaning without their owning profile. This matches the MLD guidance and simplifies cleanup.

## API Contract

### GET /api/v1/profile
- **Auth:** Required (auth:sanctum)
- **Response 200:**
```json
{
  "data": {
    "id": 1,
    "headline": "Senior Laravel Developer",
    "professional_summary": "...",
    "phone": null,
    "city": "Casablanca",
    "country": "Morocco",
    "linkedin_url": "https://linkedin.com/in/user",
    "github_url": null,
    "portfolio_url": null,
    "availability_status": "immediately",
    "target_roles": ["Laravel Developer", "Backend Engineer"],
    "preferred_locations": ["Casablanca", "Remote"],
    "work_modes": ["remote"],
    "contract_types": ["full-time", "contract"],
    "salary_min": 30000.00,
    "salary_max": 60000.00,
    "languages": [{"language": "Arabic", "proficiency": "native"}, {"language": "French", "proficiency": "fluent"}],
    "profile_completion": 65.50,
    "updated_at": "2026-07-20T12:00:00Z",
    "items": {
      "education": [...],
      "experience": [...],
      "project": [...],
      "certification": [...]
    }
  }
}
```

### PATCH /api/v1/profile
- **Auth:** Required
- **Request:** Partial profile fields
- **Response 200:** Updated profile resource
- **Response 409:** Conflict when updated_at mismatch
- **Response 422:** Validation errors
- **Note:** Creates the profile if it does not exist (upsert behavior)

### POST /api/v1/profile/items
- **Auth:** Required
- **Request:** `{ type, title, organization?, location?, start_date?, end_date?, description?, metadata? }`
- **Response 201:** Created profile item resource
- **Response 422:** Validation errors

### PATCH /api/v1/profile/items/{profileItem}
- **Auth:** Required
- **Authorization:** Route-model binding + Policy check
- **Response 200:** Updated profile item resource
- **Response 404:** Item not found or not owned by user
- **Response 409:** Concurrency conflict

### DELETE /api/v1/profile/items/{profileItem}
- **Auth:** Required
- **Response 204:** No content
- **Response 404:** Item not found or not owned by user

### PATCH /api/v1/profile/items/reorder
- **Auth:** Required
- **Request:** `{ type: "experience", item_ids: [3, 1, 2] }`
- **Response 200:** Reordered items
- **Response 422:** Validation errors (e.g., type mismatch, item not owned)
- **Transaction:** All display_order updates within one DB transaction

### Stable error codes
| Code | HTTP Status | Description |
|---|---|---|
| `profile_not_found` | 404 | Profile does not exist (returned only when specific item is missing) |
| `profile_item_not_found` | 404 | Item does not exist or not owned |
| `profile_conflict` | 409 | Stale updated_at value |
| `profile_validation_error` | 422 | Field validation failure |
| `profile_item_reorder_invalid` | 422 | Reorder validation failure |

### JSON structure documentation

**target_roles:** Array of strings, each max 100 chars, max 10 items.
```json
["Laravel Developer", "Backend Engineer"]
```

**preferred_locations:** Array of strings, each max 100 chars, max 10 items.
```json
["Casablanca", "Remote"]
```

**contract_types:** Array of strings from allowed values, max 5 items.
```json
["full-time", "part-time", "contract", "internship", "freelance"]
```

**languages:** Array of objects with language and proficiency, max 10 items.
```json
[{"language": "Arabic", "proficiency": "native"}]
```
Proficiency values: `native`, `fluent`, `advanced`, `intermediate`, `basic`

**profile_items.metadata:**
- Education: `{ "degree": "Bachelor's", "field": "Computer Science" }`
- Experience: `{ "employment_type": "full-time" }`
- Project: `{ "project_url": "https://...", "repository_url": "https://..." }`
- Certification: `{ "issuer": "AWS", "credential_url": "https://...", "expiry_date": "2028-01-01" }`

## Profile Completion Calculator

Centralized in `app/Domain/Profile/Services/ProfileCompletionService.php`.

### Weights (mandatory total 100%)
| Area | Weight | Deterministic check |
|---|---:|---|
| Basic personal information | 10% | Phone contributes 4 points, city contributes 3 points, and country contributes 3 points when each value is non-null and non-empty |
| Professional headline | 10% | Headline is non-null and non-empty |
| Professional summary | 15% | Professional summary is non-null and non-empty |
| Professional links | 10% | At least one valid LinkedIn, GitHub, or portfolio URL is present; multiple links do not add more points |
| Target roles | 15% | `target_roles` contains at least one non-empty role |
| Career preferences | 10% | Preferred locations contribute 4 points, work mode contributes 3 points, and contract types contribute 3 points |
| Languages | 10% | `languages` contains at least one valid language entry |
| Education | 10% | At least one education profile item exists |
| Practical background | 10% | At least one experience or project profile item exists; having both does not add more points |

Basic personal information and career preferences use the documented subweights above so partial data receives a deterministic partial score. Every other area is all-or-nothing. Empty strings and empty arrays do not count as populated.

Certifications are optional achievements. They contribute zero completion points, never add points above 100, and their absence never reduces an otherwise complete profile. Salary expectations and availability status are also optional and contribute zero completion points. Certifications may still be presented as a profile-strength recommendation or achievement outside the numeric calculation.

### Algorithm
```
score = 0
foreach area:
  add the area's satisfied whole or partial contribution
score = min(max(score, 0), 100)
```
Each area is independently evaluated. No double-counting.

The service returns both the bounded numeric score and an ordered area breakdown. Each breakdown entry contains a stable area key, earned points, available points, completion state, and actionable guidance when incomplete. `ProfileResource` exposes this as `completion_details`, including `missing_areas`, so the frontend renders server-derived guidance without duplicating scoring rules. Optional certifications and salary expectations are not returned as missing completion areas.

Completion is recalculated and persisted after profile upsert and after profile-item create, update, or delete. Reordering does not change qualifying data and therefore does not require recalculation.

## Backend Architecture

### Domain organization
```
app/Domain/Profile/
├── Actions/
│   ├── ShowProfileAction.php
│   ├── UpdateProfileAction.php
│   ├── CreateProfileItemAction.php
│   ├── UpdateProfileItemAction.php
│   ├── DeleteProfileItemAction.php
│   └── ReorderProfileItemsAction.php
├── Data/
│   ├── ProfileData.php
│   ├── ProfileItemData.php
│   └── ReorderData.php
├── Enums/
│   ├── ProfileItemType.php
│   ├── AvailabilityStatus.php
│   ├── WorkMode.php
│   ├── ContractType.php
│   └── LanguageProficiency.php
├── Policies/
│   └── ProfileItemPolicy.php
└── Services/
    └── ProfileCompletionService.php
```

### Controller
`ProfileController` with methods: `show`, `update`, `storeItem`, `updateItem`, `destroyItem`, `reorderItems`.

Each method: authorize → validate (Form Request) → execute (Action) → return (Resource).

### Form Requests
- `UpsertProfileRequest` — validates all profile fields for PATCH
- `StoreProfileItemRequest` — validates item creation
- `UpdateProfileItemRequest` — validates item update
- `ReorderProfileItemsRequest` — validates reorder payload

### Policies
`ProfileItemPolicy` — `view`, `create`, `update`, `delete` methods. All check `$item->candidateProfile->user_id === $user->id`.

### Routes
```php
Route::middleware(['auth:sanctum', 'throttle:120,1'])->prefix('v1')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/items', [ProfileController::class, 'storeItem']);
    Route::patch('/profile/items/reorder', [ProfileController::class, 'reorderItems']); // before {profileItem}
    Route::patch('/profile/items/{profileItem}', [ProfileController::class, 'updateItem']);
    Route::delete('/profile/items/{profileItem}', [ProfileController::class, 'destroyItem']);
});
```

### N+1 prevention
`ShowProfileAction` eager-loads: `$profile->load('items')`.

## Frontend Architecture

### Feature structure
```
frontend/src/features/profile/
├── api/
│   └── index.ts
├── components/
│   ├── ProfileHeader.vue
│   ├── CompletionGuidance.vue
│   ├── ProfessionalSummary.vue
│   ├── ExperienceTimeline.vue
│   ├── EducationSection.vue
│   ├── ProjectsSection.vue
│   ├── CertificationsSection.vue
│   ├── CareerPreferences.vue
│   ├── ProfessionalLinks.vue
│   ├── ProfileItemCard.vue
│   ├── ProfileItemForm.vue
│   ├── ReorderControls.vue
│   └── DeleteConfirmDialog.vue
├── composables/
│   └── useProfile.ts
├── pages/
│   └── ProfilePage.vue
├── schemas/
│   └── profile.ts
├── types/
│   └── index.ts
└── utils/
    └── completion.ts
```

### Route
```typescript
{
  path: '/profile',
  meta: { requiresAuth: true },
  component: DefaultLayout,
  children: [{ path: '', name: 'profile', component: () => import('@/features/profile/pages/ProfilePage.vue') }],
}
```

### Data flow
- TanStack Vue Query manages server state: query key `['profile']`, mutations for update/create/delete/reorder
- Profile store in Pinia only if cross-route access is needed (initially not needed — all profile state lives within the profile page)
- Composable `useProfile` orchestrates query, mutations, and local editing state
- Each section component manages its own edit/display state independently
- Profile completion is calculated server-side; frontend displays the value and maps it to human-readable guidance
- The frontend consumes `completion_details` and `missing_areas` from the API; it does not reproduce completion weights or scoring conditions

### UI sections
1. **ProfileHeader** — Name, headline, location, availability badge, completion bar, edit trigger
2. **CompletionGuidance** — List of completed/missing areas with actionable text
3. **ProfessionalSummary** — Read-only with "Add summary" or "Edit" action
4. **ExperienceTimeline** — Vertical timeline cards with move up/down, edit, delete
5. **EducationSection** — Cards with degree, field, institution, dates, reorder controls
6. **ProjectsSection** — Cards with project URL, repo URL in metadata
7. **CertificationsSection** — Cards with issuer, credential URL, expiry
8. **CareerPreferences** — Target roles, locations, work mode, contract types, salary range
9. **ProfessionalLinks** — LinkedIn, GitHub, portfolio URLs with validation

### Editing approach
Each section toggles between display and edit mode. Save triggers the appropriate TanStack mutation. Cancel discards local changes. A page-level navigation guard checks for unsaved changes using a composable tracking dirty state.

### Async states per section
| State | Behavior |
|---|---|
| Loading | Skeleton placeholder matching section layout |
| Empty | Prompt to add content with primary action button |
| Saving | Disabled form, saving indicator on submit button |
| Saved | Brief success toast or inline checkmark |
| Validation error | Field-level error messages, error summary |
| Server error | Inline error with retry, data preserved |
| Conflict (409) | Warning that data is stale, refresh prompt |
| Delete confirmation | Accessible dialog with cancel/confirm |

## Migrations

### create_candidate_profiles_table
```php
Schema::create('candidate_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('headline', 255)->nullable();
    $table->text('professional_summary')->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('city', 100)->nullable();
    $table->string('country', 100)->nullable();
    $table->string('linkedin_url', 500)->nullable();
    $table->string('github_url', 500)->nullable();
    $table->string('portfolio_url', 500)->nullable();
    $table->string('availability_status', 30)->nullable();
    $table->json('target_roles')->nullable();
    $table->json('preferred_locations')->nullable();
    $table->string('work_mode', 30)->nullable();
    $table->json('work_modes')->nullable();
    $table->json('contract_types')->nullable();
    $table->decimal('salary_min', 12, 2)->nullable();
    $table->decimal('salary_max', 12, 2)->nullable();
    $table->json('languages')->nullable();
    $table->decimal('profile_completion', 5, 2)->default(0);
    $table->timestamps();
});
```

### create_profile_items_table
```php
Schema::create('profile_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
    $table->string('type', 30);
    $table->string('title', 255);
    $table->string('organization', 255)->nullable();
    $table->string('location', 255)->nullable();
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->text('description')->nullable();
    $table->json('metadata')->nullable();
    $table->unsignedSmallInteger('display_order');
    $table->timestamps();

    $table->index(['candidate_profile_id', 'type', 'display_order']);
});
```

### Rollback
`Schema::dropIfExists('profile_items')` followed by `Schema::dropIfExists('candidate_profiles')`. Both tables are pure additions with no data loss risk on rollback.

## Validation Rules

Laravel Form Requests are the authoritative validation layer. Frontend validation improves usability using native form constraints and small focused TypeScript utilities with typed validation results. Those utilities remain separate from presentation components, reuse existing problem-details field-error mapping, focus the first invalid field, and preserve entered values after failed submissions. They must avoid duplicating cross-field or domain rules that could drift from Laravel; the server response remains authoritative when client and server results differ. No form-validation package is added in this change. Adoption of a validation library may be proposed separately if repeated complex forms justify it.

### Profile fields
| Field | Rules |
|---|---|
| headline | string, max:255, nullable |
| professional_summary | string, max:5000, nullable |
| phone | string, max:30, regex:/^[+\d\s\-()]+$/, nullable |
| city | string, max:100, nullable |
| country | string, max:100, nullable |
| linkedin_url | url, max:500, starts_with:https://, nullable |
| github_url | url, max:500, starts_with:https://, nullable |
| portfolio_url | url, max:500, starts_with:https://, nullable |
| availability_status | string, in:immediately,within_2_weeks,within_month,not_looking,nullable |
| target_roles | array, max:10, each string max:100 |
| preferred_locations | array, max:10, each string max:100 |
| work_modes | array, max:5, each in:remote,hybrid,on_site |
| contract_types | array, max:5, each in:full-time,part-time,contract,internship,freelance |
| salary_min | numeric, min:0, nullable |
| salary_max | numeric, min:0, nullable_with:salary_min |
| languages | array, max:10, each object with language(string max:50) and proficiency(string in:native,fluent,advanced,intermediate,basic) |

### Profile item fields
| Field | Rules |
|---|---|
| type | required, string, in:education,experience,project,certification |
| title | required, string, max:255 |
| organization | string, max:255, nullable |
| location | string, max:255, nullable |
| start_date | date, nullable |
| end_date | date, nullable, after_or_equal:start_date (when both present) |
| description | string, max:5000, nullable |
| metadata | array, nullable, max type-specific keys |
| display_order | auto-assigned on create |

## Testing Strategy

### Backend tests (Pest)
- Feature tests under `tests/Feature/Api/V1/Profile/`
- Profile CRUD: create, read, update, empty profile, unauthenticated
- Profile item CRUD: create all 4 types, update, delete, invalid types, invalid dates
- Authorization: cross-user item access returns 404
- BOLA: second user cannot see or manipulate first user's items
- Concurrency: stale updated_at returns 409
- Validation: invalid URLs, salaries, dates, types
- Overposting: privileged fields are ignored
- Profile completion: unit tests for 0%, every area and subweight independently, representative partial scores, the 0/100 bounds, and exactly 100% without certification or salary expectations
- Reordering: atomic within transaction, invalid IDs rejected
- N+1: query count assertions on profile read

### Frontend tests (Vitest)
- Profile page: loading skeleton, empty state, data display
- Section editing: save, cancel, validation errors
- Validation utilities: typed results, native constraints, first-invalid-field focus, server-error mapping, and value preservation after failed submissions
- Profile item CRUD: create all types, update, delete with confirmation
- Reorder: move up/down renders updated positions
- Network errors: save failure preserves data, shows retry
- Conflict handling: 409 shows refresh prompt
- Unsaved changes: navigation guard triggers
- Keyboard: tab through all interactive elements
- Accessibility: screen reader announcements for save/delete/errors

### Quality gates
- `vendor/bin/pint --dirty --format agent`
- `php artisan test --compact`
- `npm run lint`
- `npm run format`
- `npm run test:unit -- --run`
- `npm run build`
- `npx vue-tsc --noEmit`

## Security

### Authorization
- `auth:sanctum` middleware on all profile routes
- `ProfileItemPolicy` gates every item write/delete operation
- Route-model binding for profile items is followed by Policy check (binding alone is not authorization)
- Cross-user requests return 404

### Input security
- Form Request `passedValidation` strips privileged fields (user_id, candidate_profile_id)
- URL validation rejects javascript:, data:, file: schemes
- Phone field uses regex allowlist
- Text fields stored without HTML (no rich text in this change)
- JSON payload size limits enforced through Laravel validation

### Logging
- Log profile operation with request_id and user identifier, not payload content
- Do not log professional_summary, phone numbers, or other sensitive profile data
- Validation errors logged without full request body

### Concurrency
- `update` method on Profile uses `where('updated_at', $request->updated_at)` check
- Item mutations include updated_at comparison
- Reorder operation wrapped in DB::transaction with lock
