## Purpose

Provide a reusable server-side SSRF-safe fetcher for company research and any future remote URL retrieval, enforcing scheme, blocklist, DNS, redirect, size, type, timeout, and user-agent constraints so external fetching cannot be abused to reach internal resources.

## ADDED Requirements

### Requirement: SSRF-safe fetch with blocklist and DNS validation (FETCH-001)
The system SHALL implement a fetcher used by company research that enforces: HTTPS preferred (HTTP allowed only when HTTPS is not available and still validated, or strictly HTTPS-only per config with clear fallback), deny `file://`, `ftp://`, and other non-HTTP(S) schemes, block `localhost`, loopback (`127.0.0.0/8`, `::1`), private IPv4 (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`), private IPv6 (`fc00::/7`, `fe80::/10` link-local), link-local `169.254.0.0/16`, and cloud metadata `169.254.169.254` / `fd00:ec2::254` equivalents, validate DNS resolution for the original URL and for every redirect target before fetching, revalidate redirects, limit redirect count (e.g., 3), enforce request timeout (e.g., 5-10 s), response size limit (e.g., 1-2 MB), allowlist accepted content types (e.g., `text/html`, `text/plain`, application `text/*`, `application/xhtml+xml`), deny arbitrary binary downloads, and use a safe static `User-Agent` (e.g., `CareerPilotAI/1.0 (+https://careerpilot.example)`). Failure to satisfy any check SHALL return a safe failure without fetching the target and SHALL NOT crash the calling opportunity.

#### Scenario: Localhost blocked
- **WHEN** research requests `http://localhost/company`
- **THEN** the fetcher rejects with `fetch_blocked` / `ssrf_blocked` without issuing the HTTP request

#### Scenario: Private IPv4 127.0.0.1 blocked
- **WHEN** URL is `http://127.0.0.1/page`
- **THEN** the fetcher rejects before DNS/HTTP and returns a safe error

#### Scenario: IPv6 loopback blocked
- **WHEN** URL is `http://[::1]/page`
- **THEN** the fetcher rejects

#### Scenario: Metadata endpoint blocked
- **WHEN** URL is `http://169.254.169.254/latest/meta-data/`
- **THEN** the fetcher rejects

#### Scenario: RFC1918 ranges blocked
- **WHEN** URL is `http://192.168.1.10/internal` or `http://10.0.0.5/` or `http://172.16.5.1/`
- **THEN** the fetcher rejects each

#### Scenario: Link-local blocked
- **WHEN** URL is `http://169.254.10.20/resource`
- **THEN** the fetcher rejects

### Requirement: Redirect revalidation (FETCH-002)
The fetcher SHALL NOT follow a redirect without revalidating the redirect target against the same blocklist and DNS checks. Redirect count SHALL be limited (e.g., ≤ 3). Protocol downgrade (HTTPS → HTTP) SHALL be treated as blocked unless explicitly allowed. Redirect to private IP from a public URL SHALL be blocked.

#### Scenario: Malicious redirect to private IP blocked
- **WHEN** a public URL `https://example.com/redirect` issues a 302 to `http://127.0.0.1/admin`
- **THEN** the fetcher stops at the redirect, reports `redirect_blocked`, and does not fetch the private target

#### Scenario: Excessive redirects rejected
- **WHEN** a URL chains more than the allowed redirect count (e.g., 4)
- **THEN** the fetcher aborts with `too_many_redirects`

#### Scenario: DNS rebinding resistance
- **WHEN** DNS resolves to a public IP on first lookup but to a private IP on retry/redirect lookup
- **THEN** the fetcher re-resolves and blocks the private resolution

### Requirement: Size, type, and timeout limits (FETCH-003)
The fetcher SHALL enforce a response size limit (abort and discard when exceeded), an accepted content-type allowlist (reject `application/octet-stream` or other arbitrary binaries with safe error), and a request timeout. Oversized, unsupported-type, or timed-out responses SHALL NOT be stored as brief sources and SHALL surface as a safe fallback/failed reason.

#### Scenario: Oversized response aborted
- **WHEN** a page exceeds the size limit (e.g., 5 MB when limit is 1 MB)
- **THEN** the fetcher aborts, reports `response_too_large`, and the pipeline falls back to limited brief

#### Scenario: Unsupported MIME rejected
- **WHEN** URL returns `Content-Type: application/zip`
- **THEN** the fetcher rejects with `unsupported_content_type`

#### Scenario: Timeout handled safely
- **WHEN** a host does not respond within the timeout
- **THEN** the fetcher reports `fetch_timeout` and the pipeline produces a limited/failed brief without hanging

#### Scenario: Malformed HTML handled
- **WHEN** fetched content is truncated or malformed HTML
- **THEN** the fetcher still returns what was read up to the size limit, the parser handles it without throwing, and provenance is stored

### Requirement: No arbitrary file or scheme handling (FETCH-004)
The fetcher SHALL accept only `http://` and `https://` URLs. It SHALL NOT support `file://`, `ftp://`, `gopher://`, or other internal schemes. It SHALL NOT write fetched content to disk as a file download. Query strings and fragments SHALL be preserved but not used to bypass blocklist checks.

#### Scenario: file scheme denied
- **WHEN** URL is `file:///etc/passwd`
- **THEN** the fetcher rejects with `unsupported_scheme`

#### Scenario: ftp scheme denied
- **WHEN** URL is `ftp://example.com/file`
- **THEN** the fetcher rejects

### Requirement: Safe user-agent and failure encapsulation (FETCH-005)
The fetcher SHALL send a safe static `User-Agent` that does not leak internal details, SHALL NOT forward candidate credentials/cookies/tokens, and SHALL encapsulate all failures into a typed result (`ok` with `body`, `url`, `title`, `content_type`, `retrieved_at` or `error` with `code`/`message`) so callers never receive an unhandled exception from the fetcher.

#### Scenario: Failure does not crash pipeline
- **WHEN** fetcher returns `error: dns_failed`
- **THEN** the caller converts it to `fallback_reason: fetch_failed` or `failed` status without throwing to the HTTP layer

### Requirement: Reusable across domains (FETCH-006)
The fetcher SHALL be exposed as a reusable support utility (e.g., `App\Support\SafeWebFetch` or `App\Support\Http\SafeFetcher`) injectable into any domain, with configuration via `config/safe-fetch.php` or existing config (timeout, size limit, allowed types, redirect limit) read through config, never via direct `env()` calls in application code.

#### Scenario: Reuse from company research
- **WHEN** `ResearchCompanyJob` needs to fetch `https://example.com/about`
- **THEN** it calls the shared fetcher and receives the same validation as any other domain would

### Requirement: Observability parity (FETCH-007)
Every fetch attempt SHALL log outcome with `request_id`, `url` (redacted query if sensitive), `code`, and `duration`, and SHALL metrics-increment a `blocked_ssrf` counter on blocklist rejections. Logs SHALL NOT contain response bodies or secrets.

#### Scenario: Blocked SSRF logged safely
- **WHEN** `http://127.0.0.1/` is blocked
- **THEN** a log line contains `request_id`, `code: ssrf_blocked`, `url: http://127.0.0.1/` and no body
