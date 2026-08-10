## Why

Repeated local API use during development exhausts route throttles and blocks end-to-end testing with HTTP 429 responses. The application needs a safe, centralized local-development bypass without weakening the production rate-limit baseline or automated coverage of throttling.

## What Changes

- Add one environment-aware control for all Laravel HTTP route throttles, including numeric and named limiters.
- Disable application HTTP throttling by default only when `APP_ENV=local`; keep it enabled by default in testing, staging, production, and unknown environments.
- Allow an explicit environment override so developers can re-enable throttling locally when testing abuse controls.
- Add regression coverage proving both bypassed and enforced behavior.

### In scope

- Laravel route middleware using the `throttle` alias across authentication, profile, CV, opportunity, matching, clarification, and resume APIs.
- Local configuration, middleware registration, automated tests, and configuration documentation in `.env.example`.

### Out of scope

- Changing production rate-limit values, identities, response contracts, quotas, concurrency rules, or external provider limits.
- Frontend behavior, database schema, queues, OpenAPI paths, infrastructure, or dependency changes.

## Capabilities

### New Capabilities

- `development-rate-limiting`: Environment-safe control of application HTTP throttling for local development.

### Modified Capabilities

- None. Existing production-facing capability limits and API contracts remain unchanged.

## Impact

- **Requirement IDs:** DEV-RATE-001 through DEV-RATE-004.
- **Acceptance criteria:** local requests do not receive application-generated 429 responses when bypass is active; explicit enablement still enforces numeric and named throttles; testing and non-local defaults remain enabled.
- **Backend:** middleware alias registration, a conditional throttle middleware, configuration, environment example, and feature tests.
- **Security and abuse:** production safeguards remain enabled by default. The bypass is restricted to an explicit local environment/default and must not affect provider quotas.
- **Data/queues/API:** no migrations, persistence changes, queue changes, endpoint changes, or response-shape changes.
- **Dependencies/infrastructure:** none.
- **Rollout/rollback:** config-only behavior with no data migration; rollback removes the alias/config and restores Laravel's default throttle middleware.
- **Risk:** a misconfigured deployed environment named `local` would bypass throttling; deployment environments must use a non-local `APP_ENV`, and the explicit override can force enforcement.
- **Success measure:** repetitive local workflows across every throttled API area complete without application HTTP 429s, while enforcement tests still produce 429 when enabled.
