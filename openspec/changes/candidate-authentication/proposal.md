## Why

CareerPilot AI requires a secure, production-grade authentication foundation before any candidate-owned feature can be built. Every API endpoint, profile, opportunity, match, and resume depends on a verified identity. Without authentication, no data can be owned, protected, or personalized. This is the second change in the approved delivery order and the first that introduces domain-owned tables.

## What Changes

- Install and configure Laravel Sanctum for first-party SPA cookie authentication
- Publish and configure CORS for the separate Vue frontend origin
- Add candidate registration endpoint (`POST /api/v1/auth/register`) with email verification flow
- Add login (`POST /api/v1/auth/login`) with session regeneration
- Add logout (`POST /api/v1/auth/logout`) with session revocation
- Add current-user endpoint (`GET /api/v1/me`)
- Add forgot-password (`POST /api/v1/auth/forgot-password`) and password-reset (`POST /api/v1/auth/reset-password`) flows
- Apply rate limiting to authentication and password-reset endpoints (5 attempts/minute)
- Add Vue authentication pages: register, login, email-verify, forgot-password, reset-password
- Add Pinia auth store for frontend authentication state
- Add Vue Router navigation guards (redirect unauthenticated users to login)
- Centralize 401/419 response handling in Axios interceptor
- Create backend Pest tests for all auth endpoints (happy path, validation, rate limiting, BOLA, CSRF)
- Create frontend Vitest tests for auth pages and store
- Update `users` migration with the MLD-approved columns (full_name, role, account_status, timezone, soft deletes)
- Update OpenAPI spec and environment documentation

**BREAKING**: The existing `users` migration uses a `name` column; this change renames it to `full_name` and adds `role`, `account_status`, and `timezone` columns as defined in the MLD.

## Capabilities

### New Capabilities
- `user-registration`: Candidate self-registration with name, email, password, and email verification
- `user-authentication`: Login, logout, and session management using Sanctum SPA cookies
- `password-management`: Forgot-password and password-reset flows with secure token rotation
- `user-session`: Current authenticated user endpoint and profile-completion state
- `auth-frontend`: Vue login, registration, verification, forgot/reset pages with auth state and route guards

### Modified Capabilities

- *(None — no canonical specs exist yet)*

## Impact

**Backend modules:** Identity domain (new Actions, Data/Enums, Controllers, Requests, Resources, Policies)
**Backend files:** `app/Models/User.php`, `app/Domain/Identity/`, `app/Http/Controllers/Api/V1/AuthController.php`, `routes/api.php`, `config/sanctum.php`, `config/cors.php`
**Database:** Modify `users` table (rename `name` to `full_name`, add `role`, `account_status`, `timezone`, `deleted_at`). Update `password_reset_tokens` and `sessions` if they exist. Sanctum personal_access_tokens table only if required.
**Frontend feature:** `src/features/auth/` (pages, composables, schemas)
**Frontend files:** `src/stores/auth.ts`, `src/router/index.ts` (guards), `src/api/client/axios.ts` (interceptors)
**Configuration:** `.env.example`, `config/sanctum.php`, `config/cors.php`
**Dependencies:** Sanctum already required by Laravel, no new packages needed
**Documentation:** OpenAPI spec for auth endpoints, environment documentation update
