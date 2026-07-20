## ADDED Requirements

### Requirement: AUTH-001 — Login
The system SHALL authenticate a user by email and password using Sanctum SPA cookie sessions.
The system SHALL regenerate the session ID after successful login.
The system SHALL set the `XSRF-TOKEN` cookie and the Laravel session cookie.
The system SHALL return the authenticated user resource with HTTP 200.

#### Scenario: Successful login
- **WHEN** a POST request is sent to `/api/v1/auth/login` with valid credentials
- **THEN** the system authenticates the user, regenerates the session, sets Sanctum cookies, and returns the user resource with HTTP 200

#### Scenario: Invalid credentials
- **WHEN** a POST request is sent with an incorrect email or password
- **THEN** the system returns HTTP 422 with a generic "invalid credentials" message (no hint about which field is wrong)

#### Scenario: Login for unverified email
- **WHEN** a user with unverified email attempts to log in
- **THEN** the system allows login but includes `email_verified: false` in the response

#### Scenario: Login for suspended account
- **WHEN** a user with `account_status = suspended` attempts to log in
- **THEN** the system returns HTTP 403 with a problem-detail error indicating the account is suspended

### Requirement: AUTH-002 — Logout
The system SHALL invalidate the current session on logout.
The system SHALL clear the Sanctum cookies on the client side.

#### Scenario: Successful logout
- **WHEN** a DELETE request is sent to `/api/v1/auth/logout` with a valid session
- **THEN** the system logs the user out, invalidates the session, and returns HTTP 204

#### Scenario: Logout without active session
- **WHEN** a DELETE request is sent without an active session
- **THEN** the system returns HTTP 401

### Requirement: AUTH-003 — CSRF protection
The system SHALL expose the CSRF cookie endpoint at `/sanctum/csrf-cookie`.
The frontend SHALL call this endpoint before making state-changing requests.

#### Scenario: CSRF cookie retrieval
- **WHEN** a GET request is sent to `/sanctum/csrf-cookie`
- **THEN** the system sets the `XSRF-TOKEN` cookie

### Requirement: AUTH-004 — Login rate limiting
The system SHALL limit login attempts to 5 requests per minute per email address combined with IP address.

#### Scenario: Login rate limit exceeded
- **WHEN** more than 5 failed login attempts occur for the same email + IP pair within one minute
- **THEN** the system returns HTTP 429 with a problem-detail error

### Requirement: AUTH-005 — Session regeneration
The system SHALL rotate the session ID after every successful login.

#### Scenario: Session ID changes after login
- **WHEN** a user logs in
- **THEN** the session ID is regenerated to prevent session fixation attacks
