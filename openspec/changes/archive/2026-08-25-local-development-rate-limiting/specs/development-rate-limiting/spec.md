## ADDED Requirements

### Requirement: DEV-RATE-001 Local HTTP throttle bypass
The system SHALL bypass every Laravel route throttle that uses the `throttle` middleware alias when HTTP rate limiting is disabled by configuration.

#### Scenario: Numeric limiter is bypassed
- **WHEN** HTTP rate limiting is disabled and a local client exceeds a route's numeric attempt limit
- **THEN** the throttle middleware allows the requests to continue without an application-generated HTTP 429 response

#### Scenario: Named limiter is bypassed
- **WHEN** HTTP rate limiting is disabled and a local client exceeds a named route limiter
- **THEN** the throttle middleware allows the requests to continue without an application-generated HTTP 429 response

### Requirement: DEV-RATE-002 Safe environment defaults
The system MUST disable HTTP route throttling by default only for the `local` application environment and MUST enable it by default for testing, staging, production, and unknown environments.

#### Scenario: Local default
- **WHEN** the application environment is `local` and no explicit rate-limit override is configured
- **THEN** HTTP route throttling is disabled

#### Scenario: Non-local default
- **WHEN** the application environment is not `local` and no explicit rate-limit override is configured
- **THEN** HTTP route throttling is enabled

### Requirement: DEV-RATE-003 Explicit enforcement override
The system SHALL accept an environment configuration override that can explicitly enable or disable application HTTP route throttling.

#### Scenario: Local enforcement testing
- **WHEN** a developer explicitly enables HTTP rate limiting in the local environment
- **THEN** existing numeric and named route limits are enforced and excess requests receive the existing RFC 9457 HTTP 429 response

### Requirement: DEV-RATE-004 Scope isolation
The local HTTP throttle bypass MUST NOT alter route definitions, limiter values, ownership checks, CSRF protection, application quotas, concurrency controls, queue behavior, or external provider limits.

#### Scenario: Non-throttle controls remain active
- **WHEN** HTTP route throttling is disabled locally
- **THEN** authentication, authorization, validation, CSRF, quotas, and provider controls continue to execute unchanged
