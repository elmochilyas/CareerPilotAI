## 1. Database and Foundation

- [x] 1.1 Inspect the current `users` migration, `User` model, and database schema
- [x] 1.2 Install Sanctum: `php artisan install:api` (or publish Sanctum config manually)
- [x] 1.3 Create a migration to rename `name` to `full_name`, add `role`, `account_status`, `timezone`, and `deleted_at` columns to `users` table per MLD spec
- [x] 1.4 Add unique index on `email`, indexes on `role` and `account_status`
- [x] 1.5 Create `UserRole` and `UserAccountStatus` PHP enums in `app/Domain/Identity/Enums/`
- [x] 1.6 Update `User` model: add fillable fields, casts, relationships, and Sanctum's `HasApiTokens` trait
- [x] 1.7 Update `UserFactory` and create a `UserSeeder` with sample candidate and admin users
- [x] 1.8 Configure `config/sanctum.php`: set `stateful` domains (careerpilot-api.test, localhost:5173)
- [x] 1.9 Publish and configure `config/cors.php` for the Vue origin
- [x] 1.10 Configure `config/auth.php` to use Sanctum guard and `config/session.php` for secure cookies (SameSite, HttpOnly, secure in production)
- [x] 1.11 Update `.env.example` with Sanctum, CORS, and session configuration variables

## 2. Backend: Identity Domain Actions and DTOs

- [x] 2.1 Create `RegisterUserAction` in `app/Domain/Identity/Actions/`: validates input, creates user, dispatches email verification notification, returns user
- [x] 2.2 Create `LoginUserAction`: validates credentials, regenerates session, returns user
- [x] 2.3 Create `LogoutUserAction`: invalidates session, clears Sanctum tokens
- [x] 2.4 Create `SendPasswordResetLinkAction`: dispatches password-reset notification via Laravel's password broker
- [x] 2.5 Create `ResetPasswordAction`: validates token and updates password via password broker
- [x] 2.6 Create `ShowAuthenticatedUserAction`: returns current authenticated user
- [x] 2.7 Create `VerifyEmailAction`: marks email as verified
- [x] 2.8 Create `ResendVerificationNotificationAction`: re-sends verification email

## 3. Backend: Form Requests, Resources, and Controller

- [x] 3.1 Create `RegisterRequest` in `app/Http/Requests/Api/V1/Auth/`: validates full_name, email (unique), password (min:8, confirmed)
- [x] 3.2 Create `LoginRequest`: validates email, password
- [x] 3.3 Create `ForgotPasswordRequest`: validates email
- [x] 3.4 Create `ResetPasswordRequest`: validates email, token, password
- [x] 3.5 Create `UserResource` in `app/Http/Resources/Api/V1/`: exposes id, full_name, email, email_verified_at, role, account_status, timezone, created_at, updated_at
- [x] 3.6 Create `AuthController` in `app/Http/Controllers/Api/V1/AuthController.php` with methods: register, login, logout, forgotPassword, resetPassword, verifyEmail, resendVerification, me
- [x] 3.7 Register auth routes in `routes/api.php` under `/api/v1/auth/` prefix
- [x] 3.8 Apply `auth:sanctum` middleware on logout and me endpoints
- [x] 3.9 Apply `throttle` middleware to login (5/min), forgot-password (5/min), register (10/min) endpoints
- [x] 3.10 Register the CSRF cookie route (`/sanctum/csrf-cookie`)

## 4. Backend: Email Notifications

- [x] 4.1 Create `VerifyEmailNotification` (or use Laravel's built-in `Illuminate\Auth\Notifications\VerifyEmail`)
- [x] 4.2 Create `ResetPasswordNotification` (or use built-in `Illuminate\Auth\Notifications\ResetPassword`)
- [x] 4.3 Configure mail driver in `.env.example` (log driver for development)

## 5. Backend: Tests

- [x] 5.1 Create `RegistrationTest`: successful registration, duplicate email, weak password, missing fields, rate limiting
- [x] 5.2 Create `LoginTest`: successful login, invalid credentials, unverified email, suspended account, rate limiting, session regeneration
- [x] 5.3 Create `LogoutTest`: successful logout, unauthenticated logout
- [x] 5.4 Create `ForgotPasswordTest`: existing email, unknown email (no leak), rate limiting, invalid email
- [x] 5.5 Create `ResetPasswordTest`: valid reset, invalid/expired token, weak password, missing fields
- [x] 5.6 Create `EmailVerificationTest`: successful verification, invalid hash, resend verification
- [x] 5.7 Create `AuthenticatedUserTest`: fetch own data, unauthenticated, 401 response
- [x] 5.8 Run Pest tests and verify all pass
- [x] 5.9 Run Pint and fix code style issues

## 6. Frontend: API Client and Auth Store

- [x] 6.1 Update Axios client in `src/api/client/axios.ts`: set `withCredentials: true`, `XSRF-TOKEN` header behavior
- [x] 6.2 Add CSRF cookie fetch helper: `fetchCsrfCookie()` that calls `GET /sanctum/csrf-cookie`
- [x] 6.3 Add 401/419 interceptor: on 401 clear auth store and redirect to login; on 419 refresh CSRF cookie and retry once
- [x] 6.4 Create auth API module `src/features/auth/api.ts`: register, login, logout, forgotPassword, resetPassword, fetchUser, fetchCsrfCookie
- [x] 6.5 Create Pinia auth store `src/stores/auth.ts`: `user`, `isAuthenticated`, `isEmailVerified` state; `login`, `logout`, `fetchUser`, `checkAuth` actions
- [x] 6.6 Update `src/stores/index.ts` or `main.ts` to initialize auth store on app mount

## 7. Frontend: Auth Pages and Routing

- [x] 7.1 Create `LoginPage.vue` in `src/features/auth/pages/`: email + password form, CSRF cookie fetch before submit, error display, link to register and forgot-password
- [x] 7.2 Create `RegisterPage.vue`: full_name + email + password + password_confirmation form, validation, error display, redirect to login on success
- [x] 7.3 Create `ForgotPasswordPage.vue`: email field, success message regardless of email existence
- [x] 7.4 Create `ResetPasswordPage.vue`: email (hidden), token (hidden), password + password_confirmation fields
- [x] 7.5 Create `EmailVerificationPage.vue`: verification status, success/error display
- [x] 7.6 Update `src/router/index.ts`: add auth routes with lazy loading
- [x] 7.7 Add navigation guard `beforeEach`: redirect unauthenticated users to login for protected routes; redirect authenticated users away from login/register
- [x] 7.8 Create a `GuestLayout` and/or extend `DefaultLayout` for auth pages with minimal UI

## 8. Frontend: Tests

- [x] 8.1 Create `auth-store.spec.ts`: test login, logout, fetchUser, checkAuth actions
- [x] 8.2 Create `LoginPage.spec.ts`: render form, submit valid data, show errors, CSRF cookie fetch
- [x] 8.3 Create `RegisterPage.spec.ts`: render form, validation, error display
- [x] 8.4 Create `ForgotPasswordPage.spec.ts`: form submission, success message
- [x] 8.5 Create `ResetPasswordPage.spec.ts`: form submission, validation
- [x] 8.6 Create `axios-interceptor.spec.ts`: test 401 handling, 419 retry
- [x] 8.7 Run Vitest and verify all pass
- [x] 8.8 Run ESLint and Prettier

## 9. Documentation and Quality

- [x] 9.1 Create/update OpenAPI 3.1 spec for auth endpoints (register, login, logout, me, forgot-password, reset-password, email-verify)
- [x] 9.2 Update `docs/api/environment.md` or equivalent environment documentation with Sanctum, CORS, and session configuration details
- [x] 9.3 Verify `backend/.env.example` includes all new configuration variables
- [x] 9.4 Run full backend quality gates: Pint, Pest
- [x] 9.5 Run full frontend quality gates: ESLint, Prettier, vue-tsc, Vitest, build
- [ ] 9.6 Run `/opsx:verify` and resolve any critical findings
- [ ] 9.7 Archive the change after acceptance
