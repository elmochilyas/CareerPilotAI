## ADDED Requirements

### Requirement: FQ-HEALTH-001 — Health endpoint

The system SHALL expose a `GET /api/v1/health` endpoint that returns service status without authentication.

#### Scenario: Health check returns operational status
- **WHEN** a client sends `GET /api/v1/health`
- **THEN** the response SHALL have HTTP status 200 and JSON body containing `status` (string, `"pass"`), `timestamp` (ISO 8601 UTC), and a `services` object with at least `database` (string, `"up"` or `"down"`)

#### Scenario: Health check includes request ID
- **WHEN** a client sends `GET /api/v1/health`
- **THEN** the response SHALL include the `X-Request-ID` header and the `request_id` field in the JSON body

### Requirement: FQ-TRACE-001 — Request-ID generation and propagation

Every API response SHALL include an `X-Request-ID` header. The system SHALL generate a UUID for requests without this header and propagate it through logs and queued jobs.

#### Scenario: Request without X-Request-ID header receives a generated ID
- **WHEN** a client sends a request without the `X-Request-ID` header
- **THEN** the response SHALL contain an `X-Request-ID` header with a UUID value

#### Scenario: Request with X-Request-ID header preserves the provided ID
- **WHEN** a client sends a request with the `X-Request-ID` header set to `req-abc-123`
- **THEN** the response SHALL contain the `X-Request-ID` header with value `req-abc-123`

#### Scenario: Request ID appears in structured API response
- **WHEN** a client receives any API response
- **THEN** the JSON body SHALL include a `request_id` field matching the `X-Request-ID` header value

### Requirement: FQ-ERROR-001 — RFC 9457 problem-detail error responses

All API error responses SHALL use the RFC 9457 problem-detail format. The system SHALL never expose stack traces, internal exception messages, or debug information.

#### Scenario: Validation error returns problem detail
- **WHEN** a client sends a request with invalid data
- **THEN** the response SHALL have JSON body containing `type` (URL string), `title` (string), `status` (integer, 422), `detail` (string), `instance` (string), `code` (string), `errors` (object), and `request_id` (string)

#### Scenario: Not-found error returns problem detail
- **WHEN** a client requests a non-existent resource
- **THEN** the response SHALL have HTTP status 404 and a problem-detail body without stack trace

#### Scenario: Internal server error does not expose internals
- **WHEN** an unhandled exception occurs
- **THEN** the response SHALL have HTTP status 500 and a problem-detail body with generic `title` and no exception details, stack trace, or internal paths

#### Scenario: API response in non-production environment may include debug info
- **WHEN** `APP_DEBUG=true` and an error occurs
- **THEN** the response SHALL still include the standard problem-detail fields, and MAY include additional debug information

### Requirement: FQ-SUCCESS-001 — Consistent JSON success responses

All successful API responses SHALL use consistent JSON wrapping. The system SHALL return Eloquent API Resources for data payloads.

#### Scenario: Single resource response
- **WHEN** a client requests a single resource
- **THEN** the response SHALL have a `data` key containing the resource object

#### Scenario: Collection response
- **WHEN** a client requests a list of resources
- **THEN** the response SHALL have a `data` key containing an array of resource objects

### Requirement: FQ-GIT-001 — Protected main branch with feature branches

All development SHALL use feature branches branched from `main`. Pull requests SHALL require CI success before merge.

#### Scenario: Feature branch workflow
- **WHEN** a change is implemented
- **THEN** it SHALL be developed on a branch named `feature/<change-name>` branched from `main`

#### Scenario: PR requires CI
- **WHEN** a pull request is opened against `main`
- **THEN** CI MUST pass all quality gates before the PR can be merged

### Requirement: FQ-QUEUE-001 — Database-backed queue foundation

The system SHALL use the `database` queue driver for local development and MVP. A `jobs` table migration SHALL exist.

#### Scenario: Queue table exists
- **WHEN** migrations have run
- **THEN** the `jobs` table SHALL exist in the database

#### Scenario: Failed jobs table exists
- **WHEN** migrations have run
- **THEN** the `failed_jobs` table SHALL exist in the database

### Requirement: FQ-CACHE-001 — Cache and session configuration

The system SHALL use `file` or `database` for cache and session storage. The `.env.example` SHALL document the approved driver values.

#### Scenario: Cache driver configured
- **WHEN** the application runs
- **THEN** `CACHE_STORE` SHALL be set to `file` or `database` in non-production environments

#### Scenario: Session driver configured
- **WHEN** the application runs
- **THEN** `SESSION_DRIVER` SHALL be set to `file` or `database` in non-production environments

### Requirement: FQ-FRONT-001 — Centralized API client

The frontend SHALL use Axios with credentials enabled and a centralized RFC 9457 error mapper. TanStack Vue Query SHALL manage server state.

#### Scenario: Axios client configured for Sanctum
- **WHEN** the application sends an API request
- **THEN** Axios SHALL send credentials (`withCredentials: true`) and the `XSRF-TOKEN` cookie

#### Scenario: Problem-detail error mapping
- **WHEN** the API returns an RFC 9457 error
- **THEN** a helper function SHALL parse the error response into a typed `ProblemDetail` object

### Requirement: FQ-FRONT-002 — Accessible application shell

The frontend SHALL provide a reusable layout with semantic HTML, skip-to-content link, and responsive navigation.

#### Scenario: Skip-to-content link is present
- **WHEN** the page loads
- **THEN** a skip-to-content link SHALL be the first focusable element

#### Scenario: Semantic layout structure
- **WHEN** a page renders
- **THEN** it SHALL use `<header>`, `<main>`, and optionally `<footer>` semantic elements

### Requirement: FQ-CI-001 — GitHub Actions CI

The repository SHALL have GitHub Actions workflows that validate backend and frontend quality, run tests, and verify production builds — without requiring Redis.

#### Scenario: Backend CI workflow
- **WHEN** a push or PR occurs
- **THEN** the backend workflow SHALL run `composer validate`, `composer install`, Pint, PHPStan, and Pest tests with a MySQL service

#### Scenario: Frontend CI workflow
- **WHEN** a push or PR occurs
- **THEN** the frontend workflow SHALL run `npm ci`, ESLint, Prettier check, vue-tsc, Vitest, and the production build

### Requirement: FQ-DOCS-001 — API documentation

The API foundation SHALL have OpenAPI 3.1 documentation covering the health endpoint and error contract.

#### Scenario: OpenAPI specification exists
- **WHEN** the repository is inspected
- **THEN** an OpenAPI 3.1 document SHALL exist at `backend/resources/api/openapi.yml`
