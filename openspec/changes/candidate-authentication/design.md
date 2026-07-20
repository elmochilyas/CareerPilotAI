## Context

CareerPilot AI currently has a stock Laravel 13 scaffold with a basic `User` model, the default `users` migration (with a `name` column), and no Sanctum, CORS, or authentication infrastructure. The frontend has an Axios client, Vue Router, and TanStack Vue Query but no auth pages, store, or route guards. The Identity domain module is empty. Auth is a prerequisite for every subsequent feature.

## Goals / Non-Goals

**Goals:**
- Install and configure Sanctum for first-party SPA cookie authentication
- Configure CORS for the Vue dev server (http://localhost:5173) and production origin
- Update `users` table to match the approved MLD (full_name, role, account_status, timezone, soft deletes)
- Implement registration, email verification, login, logout, forgot-password, and password-reset endpoints
- Expose current user at `GET /api/v1/me`
- Rate-limit auth and password-reset endpoints at 5 attempts/minute
- Build Vue auth pages: register, login, email-verify, forgot-password, reset-password
- Implement Pinia auth store and Vue Router navigation guards
- Centralize 401/419 handling in the Axios interceptor
- Write backend Pest tests and frontend Vitest tests
- Update OpenAPI and environment documentation

**Non-Goals:**
- Candidate profile creation or editing
- Roles and permissions management beyond the basic `role` field
- Social login, OAuth, or third-party providers
- Two-factor authentication
- API tokens for external integrations
- CV ingestion or AI features
- Administration or user-management UI

## Decisions

1. **Sanctum SPA cookie auth over bearer tokens.** Sanctum's stateful domain mode sets session cookies via the Laravel session, eliminating the need to manage bearer tokens on the client. No tokens stored in localStorage. Matches the approved config.yaml requirement.

2. **Separate auth rate limiters.** Registration gets a higher limit (10/minute/IP) to avoid blocking legitimate signups. Login and password-reset get the stricter 5/minute limit per email+IP as specified in the config.

3. **Domain-driven Identity module.** Auth workflows live in `app/Domain/Identity/Actions/` instead of bloating controllers. Controllers remain thin: authorize (if needed), validate (Form Request), invoke the Action, return a Resource.

4. **No new packages.** Sanctum ships with Laravel. Mail notifications for verification and password-reset use Laravel's built-in `Notifiable` trait and `Illuminate\Auth\Notifications`. No additional dependency is needed.

5. **Dedicated migration for users table changes.** Rather than modifying the existing migration (which has already run), a new migration adds columns, renames `name` to `full_name`, and adds indexes.

6. **Vue auth store in Pinia.** Pinia holds the current user object and authentication state (isAuthenticated, isEmailVerified). This is one of the few appropriate uses of Pinia: UI-level session state that must be immediately available across routes. Server data (profile, opportunities, etc.) stays in TanStack Query.

7. **401/419 interceptor.** The Axios `api` client intercepts 401 (unauthenticated) and 419 (CSRF token expired) responses. On 401, it clears the Pinia auth store and redirects to login. On 419, it refreshes the CSRF cookie via `GET /sanctum/csrf-cookie` and retries the request once.

8. **Laravel's built-in password broker.** The `Password::broker()` facade manages reset token creation, validation, and email delivery. No custom token table or logic needed.

## Risks / Trade-offs

- [Stock mail config] Email verification links require a working mail driver (log driver in development). Documentation must explain how to test without a real mail server.
- [Rate-limit false positives] Multiple users behind a shared IP could hit the IP-based throttle. Acceptable for MVP; documented as a known limitation.
- [CSRF complexity] 419 retry logic adds complexity but prevents UX-breaking token expiry during long forms. Trade-off accepted for reliability.
- [Migration rollback] The users-table migration is forward-only (adding columns is safe; dropping columns or renaming back loses data). Rollback step documented.
