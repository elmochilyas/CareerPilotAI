## 1. Baseline and configuration

- [x] 1.1 Inventory numeric and named API throttle middleware and confirm the shared Laravel alias boundary.
- [x] 1.2 Review Laravel 13 rate-limiting and middleware-alias documentation.
- [x] 1.3 Add environment-safe HTTP rate-limit configuration and document the override in `.env.example`.

## 2. Backend implementation

- [x] 2.1 Add conditional throttle middleware that bypasses only when configuration is disabled and otherwise delegates to Laravel.
- [x] 2.2 Register the conditional middleware as the application `throttle` alias without changing route definitions or limiter values.

## 3. Verification

- [x] 3.1 Add feature coverage for disabled numeric and named throttles and enabled HTTP 429 enforcement.
- [x] 3.2 Run focused Pest tests, Pint, and targeted PHPStan/Larastan.
- [x] 3.3 Clear cached configuration and verify the local default is disabled while the testing default is enabled.
- [x] 3.4 Run `/opsx:verify` and resolve all critical findings.
