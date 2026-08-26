## 1. Inspection & Baseline

- [x] 1.1 Inspect `companies` model/migration/columns (`research`, `researched_at`, `company_id` on `job_opportunities`), `ConfirmOpportunityAction`, `OpportunityResource`/`CompanyResource`, `OpportunityDetailPage.vue`, existing queue jobs, AI provider abstraction, and `routes/api.php` prefix conventions; record gaps vs. proposal
- [x] 1.2 Run baseline quality gates on fresh branch: `php artisan test --compact` (backend) and `npm run type-check && npm run lint && npm run test:unit -- --run && npm run build` (frontend); capture results for before/after comparison
- [x] 1.3 Confirm `RequestIdContext` propagation path and `ProblemDetails` error code catalog to reuse for company research

## 2. Database & Config

- [x] 2.1 Create migration `add_company_research_status_to_companies_table` adding `research_status` (string 20, default `not_researched`), `research_version` (smallint default 1), `research_failure_code` nullable, `name_normalized` nullable, `website_canonical` nullable, indexes on `research_status`, `name_normalized`, `website_canonical`; verify rollback and MySQL80 compatibility
- [x] 2.2 Add `config/company-research.php` (queue=`company-research`, `stale_days=30`, `timeout=60`, `tries=3`, `backoff=[10,30,60]`, `max_input_lengths`, throttle values) and `config/safe-fetch.php` (timeout, max_bytes=1.5MB, max_redirects=3, allowed_types, enforce_https, user_agent); ensure application code reads via `Config::` only
- [x] 2.3 Update `Company` model casts/fillable (`research_status`, `research_version`, `research_failure_code`, `name_normalized`, `website_canonical`, `research` array, `researched_at` datetime) and `JobOpportunity` relation documentation; add factories/states for `Company` with research payload

## 3. Domain: Company Association & Normalization

- [x] 3.1 Implement `App\Domain\CompanyResearch\Services\CompanyResolver` with `normalizeName()` (trim, mb_strtolower, NFC, whitespace collapse, suffix strip, punctuation trim) and `canonicalizeDomain()` (IDN, lowercase, strip www/path)
- [x] 3.2 Add ambiguity guard (min length, generic stopwords, domain mismatch) and unit tests covering: "OpenAI"/"OpenAI, Inc."/" openai " reuse, "Atlas AI" vs "Atlas Systems" no-merge, domain precedence, empty/ambiguous preserves null, generic names do not merge
- [x] 3.3 Hook `CompanyResolver` into `ConfirmOpportunityAction` opportunistically (inside existing transaction, lock, never throw on ambiguity) to set `job_opportunities.company_id` when reliable; ensure confirmation still succeeds with `company_id=null` on ambiguity

## 4. Safe Web Fetch (Reusable)

- [x] 4.1 Implement `App\Support\SafeWebFetch\SafeFetcher` + `SafeFetchResult` + `SafeFetchException` with HTTPS scheme check, blocklist (localhost, 127.0.0.0/8, 10/8, 172.16/12, 192.168/16, 169.254/16, ::1, fc00::/7, fe80::/10, 169.254.169.254, 0.0.0.0, ::), scheme deny, allowed content-type prefix check
- [x] 4.2 Implement DNS resolution validation for every target and every redirect (A + AAAA), redirect loop with revalidation, max_redirects enforcement, https→http downgrade block, and response size/timeout enforcement via `Http` facade streaming
- [x] 4.3 Add `config/safe-fetch.php` wiring, safe `User-Agent`, no credential forwarding, typed error encapsulation (never throw to caller beyond typed result), and logging/metrics for `blocked_ssrf` without body
- [x] 4.4 Add unit tests for fetcher contract using `Http::fake` + DNS stubbing (or injectable resolver): localhost/127.0.0.1/::1/169.254.169.254/RFC1918/link-local blocked, redirect-to-private blocked, too_many_redirects, response_too_large, unsupported_content_type, file/ftp scheme denied, timeout, malformed HTML does not throw

## 5. Company Research Domain: Brief Model, Validation, AI & Fallback

- [x] 5.1 Define `ResearchStatus` enum (`not_researched`,`processing`,`completed`,`limited`,`failed`) and JSON brief shape (version, status, overview/products/technology_context/role_context/recent_information/candidate_preparation/sources/generated_at/fallback_reason/ai_meta) with fact|inference|unknown per claim and provenance (`url`,`title`,`retrieved_at`,`source_type`,`confidence`,`kind`,`source_ids`)
- [x] 5.2 Implement `CompanyResearchAgent` (Laravel AI `Agent+HasStructuredOutput`, `Promptable`) with delimited `<source_material>` prompt, source-constrained schema, forbidden hallucination instructions, version `1.0.0`
- [x] 5.3 Implement `CompanyResearchSchemaValidator` (evidence URL allowlist, technology-supported-by-source check, required sections, enum checks) throwing `ConflictException('invalid_ai_output')` on violation
- [x] 5.4 Implement `FallbackBriefBuilder::fromOpportunity(JobOpportunity, ?Company, array $fetchResults): array` deterministic builder (overview from trusted fields, no invented products/technologies, `unknown` markers, limited notice) and `CompanyResearchService` domain interface for future consumers
- [x] 5.5 Define contracts `CompanyResearchAnalyzer` + `FakeCompanyResearchAnalyzer` (for tests/CI) and wire `OpenAiCompanyResearchAnalyzer` via Laravel AI SDK

## 6. Async Pipeline

- [x] 6.1 Implement `StartCompanyResearchAction` (transaction, `SELECT ... FOR UPDATE` on company row, check `research_status===processing` → 409 `research_in_progress`, set `processing`, dispatch `ResearchCompanyJob::dispatch(...)->afterCommit()->onQueue(config('company-research.queue'))`, propagate `RequestIdContext`)
- [x] 6.2 Implement `Jobs\ResearchCompanyJob` (timeout 60, tries 3, backoff [10,30,60], `WithoutOverlapping` by `company-research:{companyId|oppId}`, request_id constructor arg, `handle(SafeFetcher, CompanyResearchAnalyzer, SchemaValidator, FallbackBriefBuilder)` orchestrating fetch → AI → validate → persist, `failed()` writing `research_status=failed` + `research_failure_code` without rethrow)
- [x] 6.3 Implement persistence helper that writes `companies.research` JSON + mirrors `research_status`/`researched_at`/`research_version`/`research_failure_code` atomically, setting `fallback_reason` and `ai_meta` per path (success vs. fallback)
- [x] 6.4 Implement staleness helper `isStale(): bool` (`diffInDays(researched_at) > stale_days`) and ensure `GET` resource exposes `stale`/`stale_reason` computed field

## 7. API

- [x] 7.1 Create FormRequests `StartCompanyResearchRequest` and `RefreshCompanyResearchRequest` (optional `company_website: nullable|url|max:500`, `pasted_content: nullable|string|max:20000`, allowlist validation, length limits)
- [x] 7.2 Create `CompanyResearchController` with `show` (GET), `store` (POST start), `refresh` (POST refresh) scoped to `JobOpportunityPolicy::view`, inside `Route::prefix('opportunities')` auth group; apply `throttle:company-research-*` middleware and return 202 + Location on async start, 409 on overlap, RFC 9457 problem details with stable `code` + `request_id`
- [x] 7.3 Create `CompanyResearchResource` mapping JSON brief to safe API shape (no raw HTML, escaped text, ISO 8601 UTC dates, `stale` boolean, source list); register routes and verify `php artisan route:list --path=opportunities` includes new endpoints
- [x] 7.4 Add per-user rate limiting config and concurrency guard (10/hour/user for create/refresh, concurrency 2) consistent with `job-ingestion` pattern

## 8. Policies & Authorization

- [x] 8.1 Ensure `JobOpportunityPolicy::view` covers company research access; verify cross-user `GET`/`POST` returns 404/403 per existing convention and no `company` enumeration bypasses ownership via direct company id
- [x] 8.2 Add explicit BOLA tests: owned opportunity can research, cross-user blocked (GET+POST+refresh), unauthenticated 401, invalid opportunity 404

## 9. Frontend: Opportunity Detail Integration

- [x] 9.1 Extend `frontend/src/features/opportunities/api/index.ts` with `fetchCompanyResearch`, `startCompanyResearch`, `refreshCompanyResearch` and add `CompanyResearchBrief` TypeScript types in `types/index.ts` (status, overview, products, technology_context, role_context, recent_information, candidate_preparation, sources, generated_at, fallback_reason, stale)
- [x] 9.2 Implement `composables/useCompanyResearch.ts` (useQuery for GET with `refetchInterval: status==='processing'?3000:false`, useMutation for start/refresh, invalidate on success, propagate request ID header, handle 409 as `research_in_progress` UI state)
- [x] 9.3 Implement `CompanyResearchSection.vue` + `CompanyResearchCard.vue` + subcomponents (`ResearchOverview.vue`, `ClaimLine.vue` with FACT/INFERENCE/UNKNOWN badges, `SourceChips.vue` compact + disclosure with title/url/domain/retrieved_at, safe external links) handling five states (not_researched CTA "Research company", processing busy+polling, completed six groups, failed retry, limited fallback callout)
- [x] 9.4 Wire section into `OpportunityDetailPage.vue` main column preserving two-column desktop / single-column mobile contract and sticky rail; add "Last researched: …" label, stale amber badge when `stale===true`, disabled Refresh while `processing`, and `aria-live="polite"` announcements for status transitions; ensure no `v-html`, skeletons for loading, isolated error with retry for research fetch
- [x] 9.5 Follow `CAREERPILOT_PREMIUM_DESIGN_SYSTEM` tokens (warm neutral, white card, thin border, rounded xl, indigo CTA only) and WCAG 2.2 AA (semantic headings h3 inside section, visible focus, keyboard operable, escaped source titles/URLs, safe `rel="noopener noreferrer"`)

## 10. Backend Tests (Pest)

- [x] 10.1 Feature tests: owned opportunity can request research (202 + processing), cross-user blocked, unauthenticated 401, invalid/unconfirmed opportunity behavior, company creation on reliable identity, existing company reused safely, job dispatched after commit
- [x] 10.2 State tests: concurrent research prevented (409 `research_in_progress`), successful research persisted with `researched_at` populated, refresh updates research and advances `researched_at`, staleness flag after 30 days
- [x] 10.3 Security tests: SSRF localhost blocked, private IP (10/8, 172.16/12, 192.168/16) blocked, ::1 blocked, 169.254.169.254 blocked, malicious redirect public→private blocked, oversized response rejected, unsupported MIME rejected, file/ftp scheme rejected, timeout handled, malformed HTML handled
- [x] 10.4 AI tests: schema validation rejects fabricated URL/technology, provider timeout/5xx falls back to deterministic limited brief, prompt injection string inside `<source_material>` is treated as data (no instruction follow), citations/provenance preserved, unsupported claims rejected, retry after failed research succeeds
- [x] 10.5 Normalization & storage tests: variant names reuse same company, genuinely ambiguous names do not merge, `ConfirmOpportunityAction` sets `company_id` when reliable and leaves null when uncertain without blocking confirmation, JSON shape versioned/evolvable, no raw HTML stored

## 11. Frontend Tests (Vitest + Vue Test Utils)

- [x] 11.1 Unit tests: not-researched state renders "Research company" CTA, click triggers start mutation, processing shows busy indicator + polling, completed renders six groups with fact vs inference badges, sources compact+expand with clickable domain+date, failed shows retry, limited shows fallback notice
- [x] 11.2 Interaction tests: refresh disabled while processing (no second dispatch), retry after failed transitions to processing, cross-state refresh advances stale badge, isolated error for research fetch does not blank detail page
- [x] 11.3 Accessibility tests: keyboard navigation through CTAs/links, visible focus, aria-live announcements, malicious source title escaped (no XSS via `v-html`)

## 12. Documentation & Contracts

- [x] 12.1 Update `docs/api/openapi.yaml` with `CompanyResearchBrief`, `CompanyResearchStatus` schema and three endpoints (GET/POST/POST refresh) plus problem-details error codes and request_id
- [x] 12.2 Update `docs/database/MCD.md|MLD.md|IMPLEMENTATION_PLAN.md` for added columns and research JSON shape if materially changed; update `README.md` env example with new keys (`COMPANY_RESEARCH_STALE_DAYS`, `COMPANY_RESEARCH_QUEUE`, `SAFE_FETCH_*`); add ADR if fetcher placement or storage choice is material

## 13. Quality Gates & Verification

- [x] 13.1 Run `php artisan test --compact` (or filtered to new suites) until green; run `vendor/bin/pint --dirty --format agent` then `vendor/bin/pint --test --format agent`; run `vendor/bin/phpstan analyse` (or project Larastan command) and address level 5+ findings
- [x] 13.2 Run `npm run type-check && npm run lint && npm run test:unit -- --run && npm run build` in frontend; fix remaining diagnostics; run `git diff --check` and address whitespace
- [x] 13.3 Manual verification checklist: own opportunity research → processing → completed with fact/inference badges + sources; localhost/private IP/manual redirect blocked (logs show `blocked_ssrf`); fetch unavailable → limited brief with notice; refresh → `researched_at` advances; 31-day old brief shows stale badge; cross-user 404 confirmed; queue retry leaves no stuck `processing`; OpenAPI lint passes
- [x] 13.4 Run `openspec validate --strict` and `openspec status --change company-research-brief`; resolve any critical findings and ensure `/opsx:verify` is clean before archive

