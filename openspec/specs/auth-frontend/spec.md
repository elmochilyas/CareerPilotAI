## Purpose

Provide Vue authentication pages, the auth store, route guards, and centralized 401/419 handling.

## Requirements

### Requirement: FE-001 — Registration page
The frontend SHALL provide a `/register` route with a registration form.
The form SHALL include fields for `full_name`, `email`, `password`, and `password_confirmation`.
The form SHALL validate inputs client-side before submission.
The form SHALL display server-side validation errors inline.
The form SHALL redirect to the login page with a success message after registration.
The form SHALL handle network errors and display a readable error message.

#### Scenario: Successful registration
- **WHEN** a user fills in all fields with valid data and submits
- **THEN** the page shows a success message and redirects to the login page

#### Scenario: Client-side validation error
- **WHEN** a user submits with an invalid email or short password
- **THEN** the form shows inline validation errors without making an API request

#### Scenario: Server-side error display
- **WHEN** the server returns a 422 validation error (e.g., duplicate email)
- **THEN** the form displays the error message inline next to the relevant field

### Requirement: FE-002 — Login page
The frontend SHALL provide a `/login` route with a login form.
The form SHALL include fields for `email` and `password`.
The form SHALL first call `/sanctum/csrf-cookie` before the login request.
The form SHALL redirect to the home page after successful login.
The form SHALL provide a link to the forgot-password page.

#### Scenario: Successful login
- **WHEN** a user enters valid credentials and submits
- **THEN** the frontend retrieves the CSRF cookie, sends the login request, stores auth state, and redirects to the home page

#### Scenario: Invalid credentials
- **WHEN** a user enters incorrect credentials
- **THEN** the form displays a generic "invalid credentials" error message

### Requirement: FE-003 — Email verification page
The frontend SHALL show an email verification notice after registration.
The frontend SHALL provide a `/email/verify` route that handles verification links.
The frontend SHALL show a success or error message based on the verification result.

#### Scenario: Successful verification
- **WHEN** a user clicks a valid verification link
- **THEN** the page shows a success message and enables access to all features

### Requirement: FE-004 — Forgot password page
The frontend SHALL provide a `/forgot-password` route with an email field.
The form SHALL show a success message regardless of whether the email exists.

#### Scenario: Forgot password submission
- **WHEN** a user enters their email and submits
- **THEN** the page shows a success message saying "If that email exists, we've sent a reset link"

### Requirement: FE-005 — Reset password page
The frontend SHALL provide a `/reset-password` route that accepts `email`, `token`, and new `password`.
The form SHALL include `password` and `password_confirmation` fields.

#### Scenario: Successful password reset
- **WHEN** a user submits valid token, email, and new password
- **THEN** the page shows a success message and provides a link to the login page

### Requirement: FE-006 — Authentication state management
The frontend SHALL use a Pinia store to manage authentication state.
The store SHALL expose `user`, `isAuthenticated`, and `isEmailVerified` reactive properties.
The store SHALL provide `login`, `logout`, `fetchUser`, and `checkAuth` actions.
The store SHALL initialize by calling `GET /api/v1/me` on application load.

#### Scenario: Auth store initialization
- **WHEN** the application loads
- **THEN** the auth store calls `/api/v1/me` and sets the user state if authenticated

#### Scenario: Logout clears auth state
- **WHEN** a user logs out
- **THEN** the store clears the user state and `isAuthenticated` becomes false

### Requirement: FE-007 — Route guards
The frontend SHALL redirect unauthenticated users to the login page when accessing protected routes.
The frontend SHALL NOT redirect users already on auth pages (login, register, forgot-password, reset-password).
The frontend SHALL use Vue Router `beforeEach` guards for protection.

#### Scenario: Unauthenticated access to protected route
- **WHEN** an unauthenticated user tries to access a protected route
- **THEN** the router redirects to `/login` with a `redirect` query parameter

#### Scenario: Authenticated access to login page
- **WHEN** an authenticated user tries to access `/login`
- **THEN** the router redirects to the home page

### Requirement: FE-008 — Centralized 401/419 handling
The Axios client SHALL intercept HTTP 401 responses and clear auth state.
The Axios client SHALL intercept HTTP 419 responses, refresh the CSRF cookie, and retry the request once.
The interceptor SHALL NOT cause infinite retry loops.

#### Scenario: 401 response
- **WHEN** any API request returns HTTP 401
- **THEN** the interceptor clears the auth store and redirects to `/login`

#### Scenario: 419 response
- **WHEN** any API request returns HTTP 419
- **THEN** the interceptor fetches a new CSRF cookie and retries the original request once

### Requirement: FE-009 — Accessibility
All auth forms SHALL include visible labels, keyboard navigation, focus management, and error announcements.
Error messages SHALL be linked to their respective inputs via `aria-describedby`.

#### Scenario: Error announced to screen reader
- **WHEN** a form submission fails with validation errors
- **THEN** the error messages are announced by screen readers via `aria-live` regions
