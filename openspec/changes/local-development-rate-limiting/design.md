## Context

The API currently assigns both numeric middleware parameters such as `throttle:120,1` and named limiters such as `throttle:opportunity-confirm`. Raising individual limits would be incomplete and would continually require changes as features add routes. Laravel's default `throttle` alias is the single boundary shared by these route-level controls.

The Platform/foundation area owns this development-environment behavior. No endpoint contract, domain workflow, database record, queue, or frontend state changes.

## Goals / Non-Goals

**Goals:**

- Prevent application-generated HTTP 429 responses from interrupting normal local end-to-end development.
- Cover numeric and named Laravel route throttles through one auditable control.
- Preserve the existing limiter definitions and RFC 9457 response when enforcement is enabled.
- Keep automated tests and all non-local environments protected by default.

**Non-Goals:**

- Increasing or redesigning production limits.
- Bypassing AI provider quotas, domain quotas, concurrency controls, authentication, authorization, CSRF, or validation.
- Adding dependencies, infrastructure, persistence, APIs, or frontend behavior.

## Decisions

### Central conditional middleware alias

Add `App\Http\Middleware\ConditionalThrottleRequests` and bind Laravel's `throttle` alias to it in `bootstrap/app.php`. The middleware delegates to Laravel's framework `ThrottleRequests` middleware when enabled and directly continues the pipeline when disabled. Delegation preserves framework behavior for both numeric and named limiters, response headers, keying, and exceptions.

Rejected alternatives:

- Raising each route value: incomplete, duplicates policy, and still interrupts long local sessions.
- Returning `Limit::none()` from named limiters: does not cover numeric middleware parameters.
- Removing throttle middleware from route files: weakens the visible production contract and creates drift.

### Environment-aware configuration

Add a single `rate-limiting.enabled` configuration value sourced from `RATE_LIMITING_ENABLED`. When the override is absent, it evaluates to false only for `APP_ENV=local` and true otherwise. Application code reads `config()`; environment access stays inside configuration.

The example environment file documents the explicit switch. CI uses `APP_ENV=testing`, so throttling remains enabled unless a test intentionally changes configuration.

### Verification boundary

Feature tests exercise an existing numeric limiter and an existing named limiter with configuration disabled, plus enforced behavior with configuration enabled. Tests use isolated client identities to avoid shared cache state. Existing route-level rate-limit tests continue to verify specific limits.

### Security, failures, and observability

Only the throttle alias behavior changes. All earlier and later middleware continues normally, and no sensitive data is logged. With enforcement enabled, the existing `ThrottleRequestsException` renderer remains responsible for the RFC 9457 response and request ID. There are no transactions, events, jobs, retries, idempotency changes, migrations, OpenAPI changes, or new metrics.

## Risks / Trade-offs

- A deployed environment incorrectly configured as `local` would bypass route throttling. Deployment must retain a non-local `APP_ENV`; `RATE_LIMITING_ENABLED=true` provides an explicit defense.
- Local testing will not reveal accidental route-limit regressions unless enforcement is explicitly enabled. Automated testing remains enabled by default and includes direct enforcement coverage.
- This bypass does not prevent 429 responses from OpenAI or another upstream service; those limits remain intentionally outside scope.

Rollback removes the custom alias, middleware, and config file, restoring Laravel's built-in throttle alias without data recovery work.
