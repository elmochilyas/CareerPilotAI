## Why

CareerPilot AI is a greenfield project. Before any business feature can be built, a disciplined technical foundation must exist: consistent API patterns, error handling, request tracing, database-backed queues, quality tooling, a Vue SPA shell with an API client, and CI that validates every change. Without this baseline, every subsequent change would reinvent error handling, lack request tracing, and risk inconsistent architecture. This change establishes the non-negotiable scaffold so that all future changes (candidate-authentication through security-observability-deployment) build on shared conventions.

## What Changes

- Establish a protected `main` branch Git workflow with one branch per OpenSpec change (`feature/<change-name>`) and PRs that require CI success before merge.
- Create the Laravel API foundation under `/api/v1` with a public health endpoint.
- Implement consistent JSON success responses.
- Implement RFC 9457-style API error responses so all endpoints return structured problem details.
- Generate and propagate `X-Request-ID` through the request lifecycle.
- Create the minimal modular-monolith directory structure without speculative abstractions.
- Configure database-backed queues, file/database cache, and file/database session.
- Add and configure Pest for testing, Laravel Pint for formatting, and PHPStan/Larastan for static analysis.
- Scaffold a Vue 3 application foundation with TypeScript strict mode, Vue Router, Pinia, TanStack Vue Query, and Axios.
- Create a centralized frontend API client and typed error handling for RFC 9457 responses.
- Configure Tailwind CSS and build a reusable accessible application shell.
- Configure Vitest, ESLint, Prettier, and vue-tsc for frontend quality.
- Create GitHub Actions workflows for backend tests, frontend tests, formatting, static analysis, and production build.
- Generate OpenAPI 3.1 documentation covering the API foundation.
- Document local setup for Herd, MySQL80, Vite, and database queue workers.
- Preserve all existing Laravel Boost, OpenCode, OpenSpec, and AGENTS.md configuration.

## Capabilities

### New Capabilities

This is a **foundational change** that establishes project-wide infrastructure and conventions. It does not introduce any product capability. No new `specs/<name>/spec.md` files are created because no product behavior or requirement is defined. All future changes will add their own capability specs.

### Modified Capabilities

None. This is the first change on the project; no existing capabilities exist.

## Impact

- **Backend**: New directory structure under `app/Domain/`, `app/Http/Controllers/Api/V1/`, `app/Http/Requests/Api/V1/`, `app/Http/Resources/Api/V1/`, `app/Support/ProblemDetails/`. New health endpoint. New middleware for request-ID and JSON error handling. New service providers. Configuration changes for queue, cache, and session drivers.
- **Frontend**: New directory structure under `src/app/`, `src/api/`, `src/components/`, `src/composables/`, `src/stores/`, `src/types/`, `src/assets/`. Initial router setup. Axios client with RFC 9457 error mapping. Application shell layout. Tailwind configuration.
- **Dependencies**: `pestphp/pest`, `laravel/pint`, `larastan/larastan` (backend). `vue-router`, `pinia`, `@tanstack/vue-query`, `axios`, `tailwindcss`, `vitest`, `eslint`, `prettier`, `vue-tsc` (frontend).
- **CI**: New `.github/workflows/` with backend and frontend workflows.
- **Documentation**: `docs/setup.md`, `docs/api.md` (OpenAPI), environment example files.
- **No database migrations** are created in this change — no schema changes are needed.
- **No product code** is written — authentication, profiles, skills, matching, resumes, etc. are out of scope.
