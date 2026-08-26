## ADDED Requirements

### Requirement: SES-003 - Central rejection of non-active accounts
The system SHALL centrally reject API requests that arrive with an authenticated session belonging to an account whose `account_status` is not `active`. The rejection SHALL return HTTP 403 with problem code `account_disabled` and SHALL apply to every authenticated route without per-route opt-in. The `/api/v1/me` endpoint and authentication-agnostic public routes SHALL remain reachable so clients can render a coherent sign-out experience. Password reset and email verification flows SHALL NOT require an authenticated session and therefore remain unaffected.

#### Scenario: Disabled account session rejected
- **WHEN** an account is disabled after login and the client sends any request to an authenticated route with the still-valid session cookie
- **THEN** the system returns HTTP 403 with problem code `account_disabled`

#### Scenario: Active account unaffected
- **WHEN** a user with `account_status = active` sends any request to an authenticated route
- **THEN** the request proceeds through normal authorization with no additional rejection

#### Scenario: Client can detect the disabled state
- **WHEN** a client receives the 403 `account_disabled` response on any authenticated call
- **THEN** the problem-detail body is parseable by the existing centralized error mapper so the SPA can sign out and inform the user

#### Scenario: Suspended account session rejected consistently
- **WHEN** an account with `account_status = suspended` presents an authenticated session to an authenticated route
- **THEN** the system returns HTTP 403 with problem code `account_disabled` (shared non-active enforcement)
