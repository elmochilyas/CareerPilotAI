## Context

See `proposal.md` for why. This design explains how.

**Current state inspected:**

- `companies` table exists (`backend/database/migrations/2026_07_26_000000_create_companies_table.php:14`): `id, name, website, industry, location, size_band, research JSON nullable, researched_at datetime nullable, timestamps`. No `research_status`, no index on normalized name. Unused beyond `Company` model `research` cast.
- `job_opportunities` has `company_id nullable FK`, `company_name`, `source_url`, `summary`, etc. (`2026_07_26_000003_create_job_opportunities_table.php:15`). `ConfirmOpportunityAction` creates opportunity from ingestion preview but does **not** yet set `company_id` from `overview.company` / domain.
- Routing: `Route::prefix('opportunities')` inside `auth:sanctum, active.account, throttle:120,1` group (`routes/api.php:58`). Existing confirmed controller: `JobOpportunityConfirmedController` with `index/show/preview/confirm`. Match API already uses `POST /opportunities/{opportunity}/matches → 202 + Location` pattern—reuse it.
- Queue: `database` driver, `ProcessJobIngestionJob` pattern uses `onQueue(Config::get('job-ingestion.queue'))`, `timeout 300`, `tries 3`, `backoff [10,30,60]`, `WithoutOverlapping` per ingestion, `afterCommit` dispatch in actions, `failed()` writes terminal state (`app/Jobs/ProcessJobIngestionJob.php:8`). `config/job-ingestion.php` owns queue/timeout/backoff.
- AI: `JobAnalysisAgent` implements `Agent + HasStructuredOutput` with strict prompt delimiting external content (`<job_description>` analogue), Laravel validates via `JobAnalysisSchemaValidator` and throws `ConflictException('invalid_ai_output')`. Provider abstraction via `JobAnalyzer` contract + `OpenAiJobAnalyzer`. `RequestIdContext` exists (`app/Support/RequestIdContext.php:4`). Problem details via `ProblemDetailsException/Renderer`.
- Frontend: `OpportunityDetailPage.vue` is two-column `lg:grid-cols-[minmax(0,1fr)_320px]` with sticky rail, loads via `useQuery(fetchOpportunity)` + `useMatchAnalysis`. Feature folder `frontend/src/features/opportunities/{api,components,pages,composables}`. No Company Research UI yet.
- No reusable SSRF fetcher exists; ingestion `source_url` fetching (if any) bypasses central checks—must be introduced.

Constraints: stay on Herd + MySQL80 + database queue/cache/session, no Redis/Horizon, no Docker, no new infra. Keep modular monolith boundaries, thin controllers, FormRequest+Policy+Action+Resource pattern.

## Goals / Non-Goals

**Goals:**
- Reuse `companies.research` where clean, extend minimally where provenance/status needs queryability.
- Deliver deterministic company identity reuse without fuzzy over-merge.
- Deliver SSRF-safe fetch as a **reusable** support utility, not a one-off.
- Deliver async pipeline matching existing job-ingestion/matching conventions (202 + polling + 409 on overlap + safe failed state + request-ID propagation).
- Ensure AI cannot fabricate claims: strict schema + evidence-set check + fallback deterministic brief.
- Ship OpportunityDetail Company Research section that is indistinguishable from existing design system quality.

**Non-Goals:**
- Company autocomplete/search, admin company management, or company directory.
- Web scraping crawler, scheduled refresh cron, or Redis-backed rate limiting—manual refresh only.
- Unified AI audit module (Phase H), quotas, DOCX, notifications, application tracking.
- Analytics on research quality beyond minimal metrics/logging.
- Rebuilding confirmation flow; only opportunistic `company_id` hook.

## Decisions

### 1. Storage: extend JSON, add minimal columns for status queryability

**Decision:** Keep `companies.research JSON` as primary store. Add a migration that adds `research_status ENUM('not_researched','processing','completed','limited','failed') NOT NULL DEFAULT 'not_researched'`, `research_version SMALLINT DEFAULT 1`, and optionally `research_failure_code VARCHAR(50) nullable`, plus index on `research_status`. `researched_at` already exists. JSON shape (`version,status,overview,products,technology_context,role_context,recent_information,candidate_preparation,sources,generated_at,fallback_reason,ai_meta`) remains authoritative; columns are a projection for queuing/locking and simple `WHERE` queries (avoid JSON path indexes on MySQL80). `research` JSON always mirrors column status on writes inside one transaction.

**Alternatives considered:**
- Pure JSON-only (status inside JSON). Rejected: would require `JSON_EXTRACT` in lock queries and makes `WithoutOverlapping` stale-check racy; MySQL80 JSON indexing is poor.
- New `company_research_snapshots` table. Rejected: over-engineering versus unused `companies.research`; snapshots/history not required by spec ("refresh without overwriting historical context" means keep previous JSON until replaced, not full history). Can add snapshots later without breaking API because service interface hides storage.

**Rationale:** Minimal migration, nullable-safe for existing rows, aligns with `companies` being the natural owner. Rollback is `dropColumn`; forward-fix is additive.

### 2. Company normalization & association

**Decision:** New domain service `CompanyResolver` (`app/Domain/CompanyResearch/Services/CompanyResolver.php`) with methods `normalizeName(string): string` and `resolveForOpportunity(JobOpportunity, ?string $websiteOverride): ?Company`. Steps:
1. Canonicalize website: `strtolower`, strip scheme/www., strip path/query/fragment, IDN via `idn_to_ascii` where available, validate with `FILTER_VALIDATE_DOMAIN`.
2. Normalize name: `trim`, `mb_strtolower`, `Normalizer::normalize(NFC)`, collapse `\s+` → single space, strip leading/trailing punctuation, strip suffixes via allowlist `['inc','incorporated','llc','ltd','limited','corp','corporation','co','company','group','sas','sarl','s.a.r.l','gmbh','sa','spa','bv','pty']` with surrounding `[,.\s]*` and optional trailing `.`—applied **iteratively** until stable.
3. Lookup: first by `website` canonical domain equality (`WHERE website LIKE '%domain'` normalized or exact `website_canonical` column if added), then by normalized name exact equality (computed at query via `LOWER(TRIM(...))` or stored `name_normalized` column added by migration). No Levenshtein/fuzzy.
4. Create if not found and name non-empty and not ambiguous (see below); else return null and leave `company_id` null (research proceeds via `company_name` + opportunity text).

**Ambiguity guard:** If normalized name length < 3, or name is generic stopword (`company`, `group`), or normalized collision would merge two companies with different canonical domains that are both non-null, treat as uncertain → no auto-merge, preserve opportunity.

**Hook:** `ConfirmOpportunityAction` calls `CompanyResolver::resolveOrCreate(...)` inside its existing transaction after preview resolution, before `JobOpportunity::create`. Failure to resolve never throws—just leaves `company_id = null`.

### 3. SSRF-safe fetcher: in-house wrapper over Laravel Http

**Decision:** `App\Support\SafeWebFetch\SafeFetcher` (injectable, config via `config/safe-fetch.php` read through `Config::`). Implementation:
- `fetch(string $url): SafeFetchResult` with `SafeFetchResult{ok: bool, body?: string, finalUrl?: string, title?: string, contentType?: string, retrievedAt?: Carbon, errorCode?: string}`.
- Validation order: parse `parse_url`, scheme allowlist (`https` preferred; `http` allowed but logged; config `enforce_https` defaults true with fallback to http on https failure optional), host presence, IDN conversion, DNS resolve via `dns_get_record`/`gethostbyname` for A + `dns_get_record(..., DNS_AAAA)` for AAAA, each IP validated via `filter_var(IP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)` **plus** explicit blocklist (`127.0.0.0/8`, `10/8`, `172.16/12`, `192.168/16`, `169.254/16`, `::1`, `fc00::/7`, `fe80::/10`, `169.254.169.254`). Block `0.0.0.0`, `::`, and `metadata.google.internal` style hosts via DNS check.
- Http call via `Illuminate\Support\Facades\Http::withOptions(['allow_redirects' => false, 'timeout' => Config::int('safe-fetch.timeout',5), 'headers' => ['User-Agent' => Config::string('safe-fetch.user_agent')]])`. On 3xx, extract `Location`, resolve absolute URL via `GuzzleHttp\Psr7\UriResolver` logic inline, validate target again (new DNS + IP check), increment redirect count, abort if > `max_redirects` (3). Downgrade https→http blocked.
- Streaming size: use `Http::withOptions(['stream' => true])` or simple `Http::get` then check `strlen(body)` > `max_bytes` (1_500_000) → abort and return `response_too_large`. Content-Type header validated against `allowed_types` (`text/html`, `text/plain`, `application/xhtml+xml`, `application/json` for JSON-LD fallback) via prefix match `str_starts_with`.
- No `file://` support, no cookie forwarding, no auth headers.

**Alternatives:** Guzzle middleware or package `league/uri`. Rejected: adds dependency for logic already expressible; in-house keeps auditability for SSRF tests. If later need is broader, extract interface without changing callers.

### 4. Async pipeline

**Decision:** Own domain `App\Domain\CompanyResearch` (new) with `Jobs\ResearchCompanyJob`, `Actions\StartCompanyResearchAction`, `Services\CompanyResearchService`, `Enums\ResearchStatus`. Flow:
- Controller validates (`FormRequest`: `company_website?: url|max:500`, `pasted_content?: string|max:20000`, `refresh?: bool`), authorizes via `JobOpportunityPolicy::view` (owner), checks `JobOpportunity` belongs to `Auth::user()->candidateProfile`.
- `StartCompanyResearchAction` inside transaction: lock `companies` row if exists (`SELECT ... FOR UPDATE`), check `research_status === processing` → `ConflictException('research_in_progress', 409)`. Check freshness not needed. Set `research_status = processing`, `company` row locked or opportunity's `company_id` target locked via `companies` row. Dispatch `ResearchCompanyJob::dispatch($companyIdOrOpportunityId, $requestId)->afterCommit()->onQueue(config('company-research.queue','company-research'))`.
- Job: `public int $timeout = 60; public int $tries = 3; public function backoff(): array { return [10,30,60]; } public function middleware(): array { return [new WithoutOverlapping("company-research:".($this->companyId ?? "opp:".$this->opportunityId))]; }`. Handle fetches via `SafeFetcher` (up to 3 URLs: `company.website`, opportunity `source_url` if allowlisted domain, plus candidate `pasted_content` always trusted). Then call `CompanyResearchAgent` (or deterministic fallback immediately if no fetchable evidence). Validate output via `CompanyResearchSchemaValidator`. On success: transaction writes `research` JSON + `researched_at = now()` + `research_status = completed|limited` + `research_version`. On validation/provider failure: write limited fallback brief via `FallbackBriefBuilder::fromOpportunity(...)` and set `fallback_reason`.
- Polling: `GET` returns `CompanyResearchResource` with `status, researched_at, stale, stale_reason, brief? , sources, fallback_reason, generated_at`. `stale` computed as `researched_at !== null && now()->diffInDays(researched_at) > config('company-research.stale_days',30)`.

**Idempotency:** Stable key `company-research:{companyId|oppId}` via cache lock + DB `processing` check prevents double-dispatch even without dedicated idempotency table. Duplicate POST while processing → 409.

**Request-ID:** Controller reads `X-Request-ID` from `Request::header('X-Request-ID', Str::uuid())`, sets `RequestIdContext::set()`, passes to job constructor; job restores context in `handle()`.

### 5. AI structuring

**Decision:** New `CompanyResearchAgent implements Agent, HasStructuredOutput` with `Promptable` trait, strict instructions delimited by `<source_material>` tags, version `1.0.0`, schema requiring six sections + `sources[]` with `url` enum to evidence set, per-claim `provenance: {source_ids: int[], confidence: enum(low|medium|high), kind: enum(fact|inference)}`. No free-text reasoning outside schema. `CompanyResearchSchemaValidator` mirrors `JobAnalysisSchemaValidator` style: `Validator::make` + custom evidence URL set check + technology allowlist check (technology claim must have at least one `fact` source with same technology token). On failure throw `ConflictException('invalid_ai_output')` caught by job → fallback.

**Fallback builder:** `FallbackBriefBuilder` is pure PHP determinism: overview from `company.name|website|opportunity.company_name`, description from `opportunity.summary` truncated 300 chars, products inferred **only** from explicit keywords in summary (no invention), `technology_context` left `unknown`, `role_context` generated from `opportunity.title` + department pattern `“This {title} appears to sit in {department} …”` without claiming specifics, sources = opportunity source only with `source_type: job_posting`.

**Metadata storage:** `research.ai_meta = {provider, model, prompt_version, latency_ms, tokens: {prompt, completion}, fallback_reason}` inside JSON, never overwriting `sources`. Provider abstraction via `Contracts\CompanyResearchAnalyzer` with `FakeCompanyResearchAnalyzer` for tests.

**Alternatives:** Single free-form LLM call then Laravel parsing. Rejected: hallucination risk too high; structured output + schema gate is required by spec.

### 6. API design

**Decision:** Three endpoints under `Route::prefix('opportunities')` inside existing auth group:
- `GET /api/v1/opportunities/{opportunity}/company-research` — `throttle:company-research-read` (60/min), returns 200 with `CompanyResearchResource` or 404 if opportunity not found/cross-user.
- `POST /api/v1/opportunities/{opportunity}/company-research` — `throttle:company-research-create` (10/hour/user, concurrency 2 via `WithoutOverlapping` not throttle alone), body optional `company_website`, `pasted_content`. Returns 202 with `CompanyResearchResource` (status processing) + `Location: /api/v1/opportunities/{opportunity}/company-research`. Idempotent while processing via 409.
- `POST /api/v1/opportunities/{opportunity}/company-research/refresh` — alias to same action but forces re-fetch even if `stale==false`; same throttle as create but separate key `company-research-refresh`.

Reuse `OpportunityResource` for opportunity load; new `CompanyResearchResource` for brief. Problem details via `ProblemDetailsException` with codes: `research_in_progress` (409), `not_found` (404), `forbidden` (403), `validation_error` (422), `rate_limited` (429), `fetch_failed` communicated as `status=limited|failed` not HTTP error.

OpenAPI `docs/api/openapi.yaml` updated with new paths + `CompanyResearchBrief` schema (oneOf for `completed|limited|failed`). No pagination—single resource per opportunity.

### 7. Frontend

**Decision:** Section `CompanyResearchSection.vue` inside `OpportunityDetailPage.vue` main column, above Match Brief or below it (final placement matches design spec's main-column requirement; keeps sticky rail unaffected). State machine in `useCompanyResearch` composable (`features/opportunities/composables/useCompanyResearch.ts`):
- `queryKey: ['opportunities','detail',id,'company-research']`
- `useQuery` for GET with `refetchInterval: (data?.status==='processing'? 3000 : false)` and `refetchIntervalInBackground: false`.
- `useMutation` for POST start + POST refresh, `onSuccess` → `queryClient.invalidateQueries(detail)`.
- Props to `CompanyResearchCard` which branches on `status`: `not_researched → empty CTA card`, `processing → skeleton + live region`, `completed/limited → BriefRenderer`, `failed → error + retry`.

`BriefRenderer` components: `ResearchOverview.vue`, `ResearchProducts.vue`, etc., share `ClaimLine.vue` which takes `{text, kind, source_ids, sources}` and renders FACT/INFERENCE badge (`<span class="fact-badge">`). Sources footer per section: `SourceChips.vue` with domain pills; expand via `<details>` or controlled disclosure showing `title`, clickable URL (`<a target="_blank" rel="noopener noreferrer">`), `retrieved_at` formatted via `formatDate`.

Entry points: `frontend/src/features/opportunities/api/index.ts` adds `fetchCompanyResearch`, `startCompanyResearch`, `refreshCompanyResearch`; `types/index.ts` adds `CompanyResearchBrief` TypeScript type matching OpenAPI.

No Pinia duplication—TanStack Query is source of truth. Lazy polling stops on terminal status to avoid leak.

### 8. Domain interface for future modules

**Decision:** `CompanyResearchService` with:
```php
final class CompanyResearchService {
  public function getBrief(JobOpportunity $opp): ?array;
  public function isStale(Company $c): bool;
  public function staleReason(Company $c): ?string;
}
```
Interview Preparation (`app/Domain/Interviews/Services/PrepPackGenerator`) can inject it. Service reads `companies.research` + computed `stale`; no HTTP.

## Risks / Trade-offs

- [SSRF bypass via DNS rebinding / redirect] → Mitigation: re-resolve every redirect, validate all resolved IPs, block down-grade, limit redirects, plus Security tests for localhost→public redirect, direct private IP, `169.254.169.254`.
- [AI hallucinates technologies or URLs] → Mitigation: evidence-set URL allowlist check, technology token check, strict schema, fallback deterministic builder, regression tests with prompt-injection snippet.
- [Duplicate companies from name variants] → Mitigation: deterministic normalization with suffix strip + domain canonicalization, plus ambiguity guard; migration adds `name_normalized`+`website_canonical` for exact match without fuzzy.
- [Concurrent refresh causes duplicate external fetches] → Mitigation: DB status + `WithoutOverlapping` + 409; job idempotency key prevents thundering herd even under retry.
- [MySQL80 JSON indexing perf] → Mitigation: project status into columns; JSON remains opaque, queries use indexed columns only.
- [Long AI wait blocks response] → Mitigation: job timeout 60s, t → 60; controller returns 202 < 500 ms; GET polling decouples.
- [Stale research presented as current] → Mitigation: computed `stale` boolean + `stale_reason` + UI amber badge + "Last researched" label; no auto-delete.
- [Frontend state leakage / infinite polling] → Mitigation: interval only when `processing`, cleared on unmount, `staleTime 0` with `gcTime` default; no global timer.
- [Log leakage of fetched HTML / PII] → Mitigation: fetcher logs only URL+code+duration, research logs safe fields, `Pint` rule for `logger->info` with body reviewed.

## Migration Plan

1. **Migration `2026_08_25_000010_add_company_research_status_to_companies_table.php`:**
   ```php
   Schema::table('companies', function (Blueprint $table) {
     $table->string('research_status', 20)->default('not_researched')->after('research');
     $table->unsignedSmallInteger('research_version')->default(1)->after('research_status');
     $table->string('research_failure_code', 50)->nullable()->after('researched_at');
     $table->string('name_normalized', 255)->nullable()->after('name');
     $table->string('website_canonical', 500)->nullable()->after('website');
     $table->index(['research_status']);
     $table->index(['name_normalized']);
     $table->index(['website_canonical']);
   });
   ```
   Backfill: existing rows get `research_status=not_researched` default; `name_normalized`/`website_canonical` filled via one-time `php artisan companies:normalize` command or inline in resolver lazy-fill (preferred: lazy).

2. **Config `config/company-research.php` and `config/safe-fetch.php`** (new). Add env keys: `COMPANY_RESEARCH_STALE_DAYS=30`, `COMPANY_RESEARCH_QUEUE=company-research`, `SAFE_FETCH_TIMEOUT=5`, `SAFE_FETCH_MAX_BYTES=1500000`, `SAFE_FETCH_MAX_REDIRECTS=3`.

3. **Deploy:** Additive migration, no downtime, `php artisan migrate --force`, queue worker picks up `company-research`.

4. **Rollback:** `php artisan migrate:rollback --step=1` drops added columns. JSON `research` left intact; `JobOpportunity.company_id` nullable so leave as-is. Fallback is forward-fix dropping indexes if rollback fails.

## Open Questions

- None that block spec approval. The following are resolved as documented above; if review disagrees they remain deferrable without spec change:
  - Exact staleness threshold: 30 days (configurable, documented here).
  - API path chosen as `POST /opportunities/{opportunity}/company-research` + `/refresh` rather than nested `/companies/{company}/research`—chosen because research is opportunity-scoped (candidate owns opportunity, not global company) and avoids cross-user company enumeration.
  - `SafeFetcher` location `app/Support/SafeWebFetch/` rather than `app/Http/`—keeps it support-level reusable.
  - Confirmation-time `company_id` association is opportunistic not blocking—confirmed in this design.
