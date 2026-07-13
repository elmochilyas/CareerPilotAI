## 1. Repository and environment verification

- [x] 1.1 Inspect existing backend structure, config files, routes, and test setup
- [x] 1.2 Inspect existing frontend structure, dependencies, router, and tooling
- [x] 1.3 Verify MySQL80 service is running and `careerpilot_ai` database exists
- [x] 1.4 Verify Laravel Herd serves the backend at http://careerpilot-api.test
- [x] 1.5 Run existing backend tests (`php artisan test --compact`) to establish baseline
- [x] 1.6 Run existing frontend quality checks (`npm run lint && npm run test:unit -- --run`) to establish baseline
- [x] 1.7 Create feature branch `feature/foundation-quality-baseline` from main

## 2. Backend — Modular monolith directory scaffold

- [x] 2.1 Create `app/Domain/` directory with placeholder `.gitkeep` files for each approved domain
- [x] 2.2 Create `app/Support/ProblemDetails/` directory
- [x] 2.3 Create `app/Http/Controllers/Api/V1/` directory
- [x] 2.4 Create `app/Http/Requests/Api/V1/` directory
- [x] 2.5 Create `app/Http/Resources/Api/V1/` directory
- [x] 2.6 Create `tests/Feature/Api/V1/` directory
- [x] 2.7 Create `tests/Unit/Domain/` directory

## 3. Backend — API route group and health endpoint

- [x] 3.1 Create `routes/api.php` with `/api/v1` prefix and `api` middleware group
- [x] 3.2 Add `Api\V1\HealthController` with `show()` action returning structured status response
- [x] 3.3 Register `GET /api/v1/health` route in `api.php`
- [x] 3.4 Create `app/Http/Resources/Api/V1/HealthResource` for consistent JSON wrapping
- [x] 3.5 Write feature test `tests/Feature/Api/V1/HealthTest.php` covering healthy response, database status, and request ID

## 4. Backend — Request-ID middleware

- [x] 4.1 Create `app/Http/Middleware/AddRequestId.php` middleware — accept or generate UUID, store in context, add to response header
- [x] 4.2 Create `app/Support/RequestIdContext.php` singleton for job propagation
- [x] 4.3 Register middleware in `bootstrap/app.php` for the `api` group
- [x] 4.4 Write feature test covering request-ID generation, preservation, and response presence

## 5. Backend — RFC 9457 problem-detail error handling

- [x] 5.1 Create `app/Support/ProblemDetails/ProblemDetailsException.php` — base exception with type, title, status, detail, code, errors fields
- [x] 5.2 Create `app/Support/ProblemDetails/ProblemDetailsRenderer.php` — renders exception to JSON response matching RFC 9457
- [x] 5.3 Register custom exception handler in `bootstrap/app.php` to map exceptions to problem-detail responses
- [x] 5.4 Handle 404, 401, 403, 419, 405, 422, and 500 exceptions
- [x] 5.5 Ensure unhandled exceptions with `APP_DEBUG=false` return generic 500 with no stack trace
- [x] 5.6 Write feature test `tests/Feature/Api/V1/ProblemDetailsTest.php` covering 404, 405, 500, debug mode, and request-ID behaviors

## 6. Backend — Queue, cache, and session foundation

- [x] 6.1 Verify `queue:table` and `queue:failed-table` migrations exist — jobs, job_batches, failed_jobs tables present
- [x] 6.2 Verify `session:table` migration exists — sessions table present
- [x] 6.3 Update `.env.example` to document `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`
- [x] 6.4 Add `DB_DATABASE=careerpilot_ai` and `APP_URL=http://careerpilot-api.test` to `.env.example`

## 7. Backend — Quality tooling

- [x] 7.1 Install `larastan/larastan` as dev dependency
- [x] 7.2 Create `phpstan.neon` with level 5 configuration, base paths, and scan directories
- [x] 7.3 Run `phpstan analyse` and create baseline for existing code
- [x] 7.4 Run `vendor/bin/pint --format agent` to ensure consistent code style
- [x] 7.5 Run `php artisan test --compact` to confirm all tests pass
- [x] 7.6 Add `analyse`, `lint`, and `quality` scripts to `composer.json`

## 8. Frontend — Dependencies and configuration

- [x] 8.1 Install `axios`, `@tanstack/vue-query` as dependencies
- [x] 8.2 Install and configure Tailwind CSS v4 (Vite plugin, CSS entry point with `@import "tailwindcss"`)
- [x] 8.3 Verify Tailwind CSS configuration matches the Vite plugin version

## 9. Frontend — Centralized API client

- [x] 9.1 Create `src/types/problem-detail.ts` with typed `ProblemDetail` interface matching RFC 9457
- [x] 9.2 Create `src/api/client/axios.ts` — Axios instance with `baseURL`, `withCredentials: true`, 419 CSRF retry interceptor
- [x] 9.3 Create `src/api/client/problem-detail.ts` — Axios response interceptor that parses errors into `ProblemDetail`
- [x] 9.4 Create `src/api/client/index.ts` — barrel export
- [x] 9.5 Write unit test verifying Axios interceptor correctly maps RFC 9457 error responses

## 10. Frontend — TanStack Vue Query integration

- [x] 10.1 Create `src/app/providers/query-client.ts` — Vue Query client with default options (retry, stale time)
- [x] 10.2 Register `VueQueryPlugin` in `src/main.ts`
- [x] 10.3 Write unit test verifying query client is configured

## 11. Frontend — Accessible application shell

- [x] 11.1 Create `src/components/ui/AppLayout.vue` — semantic `<header>`, `<main>`, slot-based layout
- [x] 11.2 Create `src/components/ui/SkipLink.vue` — skip-to-content link (first focusable element)
- [x] 11.3 Create `src/components/ui/NavBar.vue` — responsive navigation with branding placeholder
- [x] 11.4 Update `src/App.vue` to use `<router-view />`
- [x] 11.5 Create `src/app/layouts/DefaultLayout.vue` — default layout wrapping AppLayout with SkipLink and NavBar
- [x] 11.6 Add a basic home route at `/` rendering a welcome page in `DefaultLayout`
- [x] 11.7 Write component test for AppLayout verifying semantic structure and skip link presence

## 12. Frontend — Quality tooling

- [x] 12.1 Verify ESLint configuration covers `.vue` files and TypeScript
- [x] 12.2 Verify Prettier configuration and formatting script
- [x] 12.3 Run `npm run lint` to confirm no errors
- [x] 12.4 Run `npm run format` to confirm formatting passes
- [x] 12.5 Run `npx vue-tsc --build` to confirm type checking passes
- [x] 12.6 Run `npm run test:unit -- --run` to confirm all tests pass
- [x] 12.7 Run `npm run build` to confirm production build succeeds

## 13. GitHub Actions CI

- [x] 13.1 Create `.github/workflows/backend.yml` — triggers: push/PR to main; jobs: composer validate, Pint, PHPStan, Pest with MySQL service
- [x] 13.2 Create `.github/workflows/frontend.yml` — triggers: push/PR to main; jobs: npm ci, ESLint, Prettier, vue-tsc, Vitest, production build
- [x] 13.3 Verify CI workflows do not reference Redis, Docker, Sail, or Horizon
- [x] 13.4 Create `.github/workflows/combined.yml` — orchestrates backend + frontend for a single status check

## 14. OpenAPI and developer documentation

- [x] 14.1 Create `backend/resources/api/openapi.yml` — OpenAPI 3.1 spec covering health endpoint, error component schema, and security scheme placeholder
- [x] 14.2 Update `README.md` — local setup instructions for Herd, MySQL80, Vite, quality commands, and CI docs
- [x] 14.3 Verify `backend/.env.example` has accurate CareerPilot values (no secrets)
- [x] 14.4 Create `backend/.env.example.ci` for CI environment (MySQL, database queue, debug false)
- [x] 14.5 Verify `frontend/.env.example` exists with `VITE_API_BASE_URL=http://careerpilot-api.test`

## 15. Complete verification

- [x] 15.1 Run full backend quality suite: `vendor/bin/pint --format agent` + `phpstan analyse` + `php artisan test --compact`
- [x] 15.2 Run full frontend quality suite: `npm run lint` + `npm run format` + `npx vue-tsc --build` + `npm run test:unit -- --run` + `npm run build`
- [x] 15.3 Verify `/api/v1/health` returns expected structured response via curl
- [x] 15.4 Verify every API response contains `X-Request-ID` header and `request_id` field (via tests + curl)
- [x] 15.5 Verify API errors never expose internal details (test with invalid route, 405, 500 trigger)
- [x] 15.6 Verify no CareerPilot business feature code exists (no auth, profile, skills, matching, resumes, etc.)
- [x] 15.7 Stage all changed files, commit, and push to `feature/foundation-quality-baseline`
- [x] 15.8 Run `/opsx:verify` and resolve any critical findings
- [ ] 15.9 Create pull request to main
