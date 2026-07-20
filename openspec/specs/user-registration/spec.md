## Purpose

Allow new candidates to self-register with email verification.

## Requirements

### Requirement: REG-001 — Candidate self-registration
The system SHALL allow a new candidate to register with full name, email address, and password.
The system SHALL hash passwords using Laravel's default bcrypt algorithm.
The system SHALL assign the `candidate` role by default.
The system SHALL set `account_status` to `active` on registration.
The system SHALL send an email verification notification after successful registration.
The system SHALL return the created user resource with HTTP 201 status.

#### Scenario: Successful registration
- **WHEN** a POST request is sent to `/api/v1/auth/register` with valid `full_name`, `email`, and `password` fields
- **THEN** the system creates a user with role `candidate`, returns HTTP 201, and dispatches an email verification notification

#### Scenario: Duplicate email registration
- **WHEN** a POST request is sent with an email already registered
- **THEN** the system returns HTTP 422 with a problem-detail error indicating the email is taken

#### Scenario: Weak password rejected
- **WHEN** a POST request is sent with a password shorter than 8 characters
- **THEN** the system returns HTTP 422 with a validation error

#### Scenario: Missing required fields
- **WHEN** a POST request omits `full_name`, `email`, or `password`
- **THEN** the system returns HTTP 422 with validation errors for each missing field

### Requirement: REG-002 — Email verification
The system SHALL generate a signed or hashed email verification URL using Laravel's built-in verification mechanism.
The system SHALL mark the user as verified when the verification URL is accessed.
The system SHALL expose the verification status on the user resource.

#### Scenario: Successful email verification
- **WHEN** a user clicks the verification link
- **THEN** the system marks `email_verified_at` as the current timestamp and redirects to the frontend success page

#### Scenario: Verification with invalid hash
- **WHEN** a user clicks a verification link with an invalid or tampered hash
- **THEN** the system returns HTTP 403 with a problem-detail error

#### Scenario: Re-sending verification email
- **WHEN** a user requests to re-send the verification email
- **THEN** the system sends a new verification notification and returns HTTP 202

### Requirement: REG-003 — Registration rate limiting
The system SHALL limit registration attempts to 10 requests per minute per IP address.

#### Scenario: Registration rate limit exceeded
- **WHEN** more than 10 registration requests are sent from the same IP within one minute
- **THEN** the system returns HTTP 429 with a problem-detail error
