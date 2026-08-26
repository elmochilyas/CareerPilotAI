## MODIFIED Requirements

### Requirement: AUTH-001 — Login
The system SHALL authenticate a user by email and password using Sanctum SPA cookie sessions.
The system SHALL regenerate the session ID after successful login.
The system SHALL set the `XSRF-TOKEN` cookie and the Laravel session cookie.
The system SHALL return the authenticated user resource with HTTP 200.
A disabled account (`account_status = disabled`) SHALL NOT be able to create a new authenticated session. The disabled check SHALL run only after credential verification so enumeration behavior is unchanged. Email verification state SHALL NOT block or alter the disabled rejection, and password reset endpoints SHALL remain usable for a disabled account without revealing account status beyond the documented error contract.

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

#### Scenario: Login for disabled account
- **WHEN** a POST request is sent to `/api/v1/auth/login` with valid credentials for a user whose `account_status = disabled`
- **THEN** the system returns HTTP 403 with problem code `account_disabled` and does not create an authenticated session

#### Scenario: Disabled rejection does not aid enumeration
- **WHEN** a POST request is sent with an unknown email or a wrong password
- **THEN** the response is identical to any other invalid-credentials attempt regardless of whether a disabled account exists at that email

#### Scenario: Active accounts unaffected
- **WHEN** a user with `account_status = active` logs in with valid credentials
- **THEN** behavior is unchanged: HTTP 200, session created, cookies set
