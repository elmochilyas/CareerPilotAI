## Context

CareerPilot AI is a greenfield monorepo with a Laravel 13 backend (PHP 8.4) and Vue 3 SPA frontend (TypeScript, Vite). The current state after initial `composer create-project` / `npm create vue` scaffolding:

**Backend** – Default Laravel skeleton with:
- Pest 4, Pint 1, Laravel Boost configured.
- Queue `default=database`, cache `default=database`, session `driver=database`.
- No `api.php` routes file, no `/api/v1` prefix, no Domain structure.
- No request-ID middleware, no RFC 9457 error handling, no health endpoint.
- No PHPStan/Larastan configured.
- `routes/web.php` (default welcome route) and `routes/console.php` exist.

**Frontend** – Default Vue 3 + TypeScript + Vite scaffolding with:
- Vue Router 5, Pinia 3, Vitest 4, ESLint 10, Prettier, vue-tsc 3, oxlint configured.
- **Missing**: Axios, TanStack Vue Query, Tailwind CSS.
- Router has zero routes. App.vue is the default "You did it!" page.
- No centralized API client, no RFC 9457 error handling.

**CI** – No `.github/workflows/` yet.

**Documentation** – Default README files. No environment example beyond `.env.example`.

No product business logic exists, and none should be introduced by this change.

## Goals / Non-Goals

**Goals:**
- Establish a protected `main` branch with one branch per OpenSpec change (`feature/<change-name>`), PRs requiring CI success.
- Create `/api/v1` route group with consistent JSON success responses.
- Implement a `GET /api/v1/health` endpoint returning service status.
- Implement RFC 9457 problem-detail error responses that never expose internals.
- Generate and propagate `X-Request-ID` across request, logs, and jobs.
- Create `app/Support/ProblemDetails/` and `app/Domain/` scaffold with directory placeholders.
- Add `larastan/larastan` for static analysis.
- Add `phpstan.neon` configuration with baseline.
- Add database queue, cache, and session migration if missing.
- Add `api.php` route file with `api/v1` prefix.
- Install Axios and TanStack Vue Query on frontend.
- Create centralized Axios client with Sanctum cookie support and RFC 9457 error mapper.
- Install and configure Tailwind CSS with a minimal design-token theme.
- Build a reusable accessible application shell (AppLayout, NavBar, SkipLink).
- Configure Vitest with Vue Test Utils for component tests.
- Configure GitHub Actions: backend (composer, Pint, PHPStan, Pest + MySQL), frontend (npm, ESLint, Prettier, vue-tsc, Vitest, build).
- Generate OpenAPI 3.1 spec for the health endpoint and error contract.
- Write local setup docs matching Herd + MySQL80 environment.
- Preserve existing AGENTS.md, opencode.json, boost.json, Laravel Boost block.

**Non-Goals:**
- No authentication, user registration, or session endpoints.
- No candidate profiles, skills, CV ingestion, job opportunities.
- No AI analysis, matching, resume generation, or interviews.
- No application tracking, learning roadmap, or notifications.
- No production deployment, Docker, Redis, Horizon, Sail.
- No database schema changes for business entities.
- No feature-specific Vue pages or components beyond the shell.

## Decisions

| Decision | Choice | Alternatives Considered |
|---|---|---|
| Error format | RFC 9457 problem details via custom `ProblemDetailsException` and `Renderable` interface | JSON:API errors – RFC 9457 is simpler, specified in config.yaml, and widely adopted |
| Request-ID | Custom middleware generating UUID, accepting `X-Request-ID` header, storing in `Context` singleton for job propagation | Per-request `Log::withContext()` – singleton allows job-passing without coupling to Log facade |
| Health endpoint | Simple `HealthController` returning `{ status, timestamp, services: { database } }` | Third-party health packages – overkill for MVP, one controller is sufficient |
| API versioning | `/api/v1` prefix in `routes/api.php` | Header-based versioning – URL prefix is explicit, cache-friendly, and matches config.yaml |
| PHPStan | `larastan/larastan` with level 5, baseline for existing code | Level max – too strict for a new project; level 5 catches real bugs without blocking productivity |
| Frontend API client | Axios with `withCredentials: true`, `XSRF-TOKEN` interceptor, and centralized `ProblemDetail` type | Native `fetch()` – Axios provides interceptors, request cancellation, and better error handling |
| Server state | TanStack Vue Query | Manual Pinia fetching – Vue Query provides caching, retries, invalidation, and loading states |
| Tailwind | Tailwind CSS v4 with design tokens (colors, spacing, fonts) | Plain CSS, utility-only – tokens ensure consistency across future feature work |
| Application shell | Semantic `<header>`, `<main>`, `<footer>` with skip-to-content link, responsive nav | Complex headless UI library – semantic HTML is sufficient for MVP and accessible by default |
| CI database | MySQL service (no Redis) | SQLite – MySQL matches production; config.yaml requires it |
| Queue verification | Database queue migration, helper artisan command for `queue:listen` documentation | Horizon – forbidden by config.yaml without measured need |

## Risks / Trade-offs

- **[Dependency management]** Adding `larastan/larastan`, `axios`, `@tanstack/vue-query`, and Tailwind increases baseline dependencies. → Mitigation: these are standard Laravel/Vue ecosystem packages documented in config.yaml.
- **[Tailwind v4 compatibility]** Tailwind CSS v4 has a new configuration approach (CSS-based instead of `tailwind.config.js`). → Mitigation: check installed Vite plugin version and use the supported configuration format.
- **[Git workflow enforcement]** The protected-main workflow requires developer discipline. → Mitigation: GitHub branch protection rules enforce PR review and CI success. Documented in setup docs.
- **[Laravel 13 specifics]** Laravel 13 may have differences in route file structure, middleware, or config format vs Laravel 11/12. → Always use `laravel-boost search-docs` before writing implementation code.
- **[Vue Router 5]** Vue Router v5 has Composition API-first API. The existing scaffolding uses v5. → Use the Composition API pattern `createRouter` as already established.

## Migration Plan

This change does not deploy to production. All changes are local development scaffolding:
1. Create feature branch `feature/foundation-quality-baseline`.
2. Implement backend foundation (health endpoint, error handling, request-ID, routes, PHPStan).
3. Verify backend quality gates.
4. Implement frontend foundation (Axios, Vue Query, Tailwind, shell, API client).
5. Verify frontend quality gates.
6. Create GitHub Actions workflows.
7. Generate OpenAPI doc and local setup docs.
8. Run full verification suite.
9. Create PR and merge to main after CI success.

Rollback: revert the feature branch and delete it. No data migrations exist to reverse.

## Open Questions

None. All decisions are documented in config.yaml and AGENTS.md.
