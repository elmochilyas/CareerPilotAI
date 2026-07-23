## ADDED Requirements

### Requirement: PWD-001 — Forgot password
The system SHALL accept an email address at `/api/v1/auth/forgot-password`.
The system SHALL send a password-reset notification if the email exists in the system.
The system SHALL NOT reveal whether the email exists in the response.
The system SHALL rate-limit password-reset requests to 5 per minute per email address + IP.

#### Scenario: Forgot password for existing email
- **WHEN** a POST request is sent to `/api/v1/auth/forgot-password` with a registered email
- **THEN** the system sends a password-reset notification and returns HTTP 202

#### Scenario: Forgot password for unknown email
- **WHEN** a POST request is sent to `/api/v1/auth/forgot-password` with an unregistered email
- **THEN** the system returns HTTP 202 without sending any notification (no hint about email existence)

#### Scenario: Forgot password rate limit exceeded
- **WHEN** more than 5 forgot-password requests occur for the same email + IP within one minute
- **THEN** the system returns HTTP 429 with a problem-detail error

#### Scenario: Invalid email format
- **WHEN** a POST request is sent with an invalid email format
- **THEN** the system returns HTTP 422 with a validation error

### Requirement: PWD-002 — Password reset
The system SHALL accept a token, email, and new password at `/api/v1/auth/reset-password`.
The system SHALL validate the reset token using Laravel's password broker.
The system SHALL update the password on successful token validation.
The system SHALL invalidate the reset token after use.
The system SHALL return HTTP 200 on success.

#### Scenario: Successful password reset
- **WHEN** a POST request is sent to `/api/v1/auth/reset-password` with a valid token, email, and new password
- **THEN** the system updates the password and returns HTTP 200

#### Scenario: Invalid or expired reset token
- **WHEN** a POST request is sent with an invalid or expired token
- **THEN** the system returns HTTP 422 with a problem-detail error

#### Scenario: Weak new password
- **WHEN** a POST request is sent with a new password shorter than 8 characters
- **THEN** the system returns HTTP 422 with a validation error

#### Scenario: Missing fields
- **WHEN** a POST request omits `email`, `token`, or `password`
- **THEN** the system returns HTTP 422 with validation errors
