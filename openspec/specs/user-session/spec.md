## Purpose

Expose the current authenticated user and apply rate limiting to authenticated endpoints.

## Requirements

### Requirement: SES-001 — Current authenticated user
The system SHALL expose the authenticated user's data at `GET /api/v1/me`.
The response SHALL include `id`, `full_name`, `email`, `email_verified_at`, `role`, `account_status`, `timezone`, `created_at`, and `updated_at`.
The endpoint SHALL require a valid Sanctum session.
The system SHALL return the user resource wrapped in a standard API Resource.

#### Scenario: Authenticated user retrieves own data
- **WHEN** a GET request is sent to `/api/v1/me` with a valid session
- **THEN** the system returns HTTP 200 with the user resource

#### Scenario: Unauthenticated user
- **WHEN** a GET request is sent to `/api/v1/me` without a valid session
- **THEN** the system returns HTTP 401

#### Scenario: Different user cannot access another's session data
- **WHEN** user A attempts to access `/api/v1/me` while authenticated as user A
- **THEN** user A receives only their own data (enforced by session, not by ID parameter)

### Requirement: SES-002 — Rate limiting for authenticated endpoints
Authenticated endpoints SHALL be rate-limited to 120 reads per minute.

#### Scenario: Rate limit for authenticated reads
- **WHEN** more than 120 authenticated requests are sent within one minute
- **THEN** the system returns HTTP 429 with a problem-detail error
