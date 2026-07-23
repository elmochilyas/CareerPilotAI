## Why

CareerPilot AI's core promise is "one trusted profile, many tailored applications." Without a structured professional profile, no downstream feature — matching, resume generation, application tracking, or learning roadmaps — can function. The existing authentication system creates users, but candidates have no way to represent who they are professionally. This change establishes the foundational profile capability that every subsequent feature depends on.

## What Changes

- Introduce `candidate_profiles` and `profile_items` database tables
- Create a versioned REST API under `/api/v1` for profile and profile-item CRUD
- Build a deterministic profile-completion calculator with documented weights
- Add backend authorization, validation, concurrency, and privacy controls
- Build a premium-quality Vue 3 profile interface with section-based editing
- Add loading, empty, validation, conflict, error, and retry states throughout
- Reuse existing Sanctum cookie authentication — no auth redesign
- Do not introduce skills, CV ingestion, AI, file uploads, version history, or public profiles

## Capabilities

### New Capabilities
- `profile-crud`: Create, read, and update the authenticated candidate's master profile — headline, summary, contact links, career preferences, and languages
- `profile-items`: Manage typed profile records (education, experience, project, certification) through add, edit, delete, and reorder operations
- `profile-completion`: Deterministic percentage calculation based on populated profile areas with documented weights, centralized in a testable service
- `profile-ui`: Authenticated Vue 3 single-page profile interface with section editing, completion guidance, item timelines, and career preferences

### Modified Capabilities
- `user-session`: The `/api/v1/me` endpoint may optionally return profile-completion state in the future, but this change does not modify authentication behavior

## Impact

### Backend
- New files under `app/Domain/Profile/` (Actions, Data, Enums, Policies, Services)
- New `ProfileController` in `app/Http/Controllers/Api/V1/`
- New Form Requests under `app/Http/Requests/Api/V1/Profile/`
- New API Resources under `app/Http/Resources/Api/V1/`
- New routes in `routes/api.php` under the authenticated `auth:sanctum` group
- Two new migrations: `create_candidate_profiles_table`, `create_profile_items_table`
- New factories for `CandidateProfile` and `ProfileItem` models
- New models: `CandidateProfile`, `ProfileItem`
- Updated `User` model with `hasOne` relationship

### Frontend
- New feature folder `frontend/src/features/profile/` with api, components, composables, pages, schemas, types, utils subdirectories
- New route `/profile` with lazy-loaded page component
- New Pinia store for profile state where cross-route state is strictly necessary
- Profile query and mutation definitions using TanStack Vue Query
- Section-editing components for each profile area
- Accessible reordering controls for profile items
- Profile-completion guidance component
- No new npm dependencies required

### Database
- `candidate_profiles` table: user_id (unique FK), headline, professional_summary, phone, city, country, linkedin_url, github_url, portfolio_url, availability_status, target_roles (JSON), preferred_locations (JSON), work_modes (JSON), contract_types (JSON), salary_min/max, languages (JSON), profile_completion
- `profile_items` table: candidate_profile_id (FK), type (education|experience|project|certification), title, organization, location, start_date, end_date, description, metadata (JSON), display_order
- Both tables use InnoDB, utf8mb4, foreign keys with cascade on parent delete, explicit check constraints

### API
- `GET /api/v1/profile` — return authenticated candidate's profile and grouped items
- `PATCH /api/v1/profile` — create or update profile (PATCH semantics: partial update)
- `POST /api/v1/profile/items` — create a profile item
- `PATCH /api/v1/profile/items/{profileItem}` — update a profile item
- `DELETE /api/v1/profile/items/{profileItem}` — delete a profile item
- `PATCH /api/v1/profile/items/reorder` — atomically reorder items within a type

### Security
- Policy-based ownership enforcement on every profile-item operation
- Cross-user ID access returns 404 (consistent not-found, not 403)
- Reject overposting of user_id, candidate_profile_id, or other privileged fields
- Validate URLs against unsafe schemes (javascript:, data:, file:)
- Sanitize text fields for safe HTML-free rendering
- Do not log sensitive profile content
- Use `updated_at` optimistic concurrency to prevent silent overwrites

### Documentation
- OpenAPI 3.1 contract updated with all new endpoints, request/response schemas, error codes, and examples
- Profile-completion rules documented in specifications
- Allowed status values, validation limits, metadata JSON structures documented

### Risks
- Scope creep into skills or version history: explicitly excluded and enforced through review
- Database design conflict with downstream features: profile_items metadata must be extensible; JSON metadata approach mitigates this
- Frontend complexity from section editing: mitigated by focused component decomposition and shared UI patterns
