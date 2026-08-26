<?php

namespace App\Support\SafeWebFetch;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SafeFetcher
{
    /**
     * Fetch URL with SSRF protections.
     */
    public function fetch(string $url): SafeFetchResult
    {
        $url = trim($url);

        if ($url === '') {
            return SafeFetchResult::failure('invalid_url', 'URL is empty.');
        }

        if (mb_strlen($url) > 2048) {
            return SafeFetchResult::failure('invalid_url', 'URL too long.');
        }

        $maxRedirects = Config::integer('safe-fetch.max_redirects', 3);
        $timeout = Config::integer('safe-fetch.timeout', 5);
        $maxBytes = Config::integer('safe-fetch.max_bytes', 1500000);
        $allowedTypes = Config::array('safe-fetch.allowed_content_types', ['text/html', 'text/plain', 'application/xhtml+xml', 'application/json']);
        $enforceHttps = Config::boolean('safe-fetch.enforce_https', true);
        $userAgent = Config::string('safe-fetch.user_agent', 'CareerPilotAI/1.0 (+https://careerpilot.example)');

        $currentUrl = $url;
        $redirectCount = 0;
        $originalScheme = null;

        while (true) {
            $validation = $this->validateUrl($currentUrl, $enforceHttps, $originalScheme);

            if ($validation !== null) {
                $this->logBlocked($currentUrl, $validation['code']);

                return SafeFetchResult::failure($validation['code'], $validation['message']);
            }

            if ($originalScheme === null) {
                $originalScheme = (string) parse_url($currentUrl, PHP_URL_SCHEME);
                $originalScheme = strtolower($originalScheme);
            }

            // Check DNS/IP block for current URL host
            $host = $this->extractHost($currentUrl);

            if ($host !== null) {
                $blockReason = $this->getBlockReasonForHost($host);

                if ($blockReason !== null) {
                    $this->logBlocked($currentUrl, $blockReason['code']);

                    return SafeFetchResult::failure($blockReason['code'], $blockReason['message']);
                }
            }

            try {
                $response = Http::withOptions([
                    'allow_redirects' => false,
                    'timeout' => $timeout,
                    'connect_timeout' => $timeout,
                    'http_errors' => false,
                ])
                    ->withHeaders([
                        'User-Agent' => $userAgent,
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.5',
                    ])
                    ->get($currentUrl);
            } catch (\Throwable $e) {
                Log::warning('Safe fetch failed', [
                    'url' => $this->redactUrl($currentUrl),
                    'error' => $e->getMessage(),
                ]);

                if (str_contains(strtolower($e->getMessage()), 'timed out') || str_contains(strtolower($e->getMessage()), 'timeout')) {
                    return SafeFetchResult::failure('fetch_timeout', 'Request timed out.');
                }

                return SafeFetchResult::failure('fetch_failed', 'Failed to fetch URL: '.$e->getMessage());
            }

            $status = $response->status();

            // Handle redirects
            if ($status >= 300 && $status < 400) {
                $location = $response->header('Location');

                if ($location === null || trim($location) === '') {
                    return SafeFetchResult::failure('redirect_failed', 'Redirect without Location.');
                }

                $redirectCount++;

                if ($redirectCount > $maxRedirects) {
                    $this->logBlocked($currentUrl, 'too_many_redirects');

                    return SafeFetchResult::failure('too_many_redirects', 'Too many redirects.');
                }

                $nextUrl = $this->resolveRedirectUrl($currentUrl, trim($location));

                if ($nextUrl === null) {
                    return SafeFetchResult::failure('redirect_failed', 'Invalid redirect URL.');
                }

                // Check downgrade https -> http
                $nextScheme = strtolower((string) parse_url($nextUrl, PHP_URL_SCHEME));

                if ($originalScheme === 'https' && $nextScheme === 'http') {
                    $this->logBlocked($nextUrl, 'redirect_blocked');

                    return SafeFetchResult::failure('redirect_blocked', 'HTTPS downgrade redirect blocked.');
                }

                // Validate next URL host before following
                $nextHost = $this->extractHost($nextUrl);

                if ($nextHost !== null) {
                    $blockReason = $this->getBlockReasonForHost($nextHost);

                    if ($blockReason !== null) {
                        $this->logBlocked($nextUrl, 'redirect_blocked');

                        return SafeFetchResult::failure('redirect_blocked', 'Redirect to private/internal resource blocked.');
                    }
                }

                $currentUrl = $nextUrl;

                continue;
            }

            // Non-redirect response
            if ($status < 200 || $status >= 300) {
                return SafeFetchResult::failure('http_error', 'HTTP error: '.$status);
            }

            // Pre-check Content-Length before buffering body (DoS mitigation)
            $contentLength = $response->header('Content-Length');

            if ($contentLength !== null && is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
                return SafeFetchResult::failure('response_too_large', 'Response exceeds size limit.');
            }

            $contentTypeHeader = $response->header('Content-Type') ?? '';
            $contentType = strtolower(trim(explode(';', $contentTypeHeader)[0] ?? ''));

            if ($contentType !== '' && ! $this->isAllowedContentType($contentType, $allowedTypes)) {
                return SafeFetchResult::failure('unsupported_content_type', 'Content-Type not allowed: '.$contentType);
            }

            $body = (string) $response->body();

            if (strlen($body) > $maxBytes) {
                return SafeFetchResult::failure('response_too_large', 'Response exceeds size limit.');
            }

            $title = $this->extractTitle($body);

            return SafeFetchResult::success(
                body: $body,
                finalUrl: $currentUrl,
                title: $title,
                contentType: $contentType !== '' ? $contentType : null,
                retrievedAt: Carbon::now(),
            );
        }
    }

    /**
     * @return array{code: string, message: string}|null
     */
    protected function validateUrl(string $url, bool $enforceHttps, ?string $originalScheme): ?array
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return ['code' => 'invalid_url', 'message' => 'Invalid URL.'];
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';

        if (! in_array($scheme, ['http', 'https'], true)) {
            if ($scheme === '') {
                return ['code' => 'unsupported_scheme', 'message' => 'Missing URL scheme.'];
            }

            return ['code' => 'unsupported_scheme', 'message' => 'Unsupported scheme: '.$scheme];
        }

        // enforceHttps blocks https->http downgrade redirects (see fetch loop), but initial http is allowed
        // per AGENTS.md "Allow public HTTP(S) only" — initial http is not rejected; downgrade only blocked.

        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['code' => 'invalid_url', 'message' => 'URL with credentials not allowed.'];
        }

        $host = $parts['host'] ?? null;

        if ($host === null || trim((string) $host) === '') {
            return ['code' => 'invalid_url', 'message' => 'URL missing host.'];
        }

        $host = strtolower(trim((string) $host));

        // Block obvious localhost strings before DNS
        if ($host === 'localhost' || $host === 'metadata.google.internal' || str_ends_with($host, '.localhost')) {
            return ['code' => 'ssrf_blocked', 'message' => 'Host blocked.'];
        }

        // Block metadata IP literal quickly
        if ($host === '169.254.169.254' || $host === '[169.254.169.254]') {
            return ['code' => 'ssrf_blocked', 'message' => 'Host blocked.'];
        }

        // Check if host itself is an IP literal and blocked
        $unbracketed = trim($host, '[]');

        if (filter_var($unbracketed, FILTER_VALIDATE_IP) !== false) {
            if ($this->isIpBlocked($unbracketed)) {
                return ['code' => 'ssrf_blocked', 'message' => 'IP blocked.'];
            }
        }

        return null;
    }

    protected function extractHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower(trim($host));
    }

    /**
     * @return array{code: string, message: string}|null
     */
    protected function getBlockReasonForHost(string $host): ?array
    {
        $unbracketed = trim($host, '[]');

        // Direct IP literal
        if (filter_var($unbracketed, FILTER_VALIDATE_IP) !== false) {
            if ($this->isIpBlocked($unbracketed)) {
                return ['code' => 'ssrf_blocked', 'message' => 'IP blocked.'];
            }

            return null;
        }

        // Domain -> resolve to IPs
        $ips = $this->resolveIps($host);

        foreach ($ips as $ip) {
            if ($this->isIpBlocked($ip)) {
                return ['code' => 'ssrf_blocked', 'message' => 'Resolved IP blocked: '.$ip];
            }
        }

        // Explicit metadata hostname
        if ($host === '169.254.169.254' || $host === 'metadata.google.internal') {
            return ['code' => 'ssrf_blocked', 'message' => 'Host blocked.'];
        }

        return null;
    }

    /**
     * Resolve host to IPs (A + AAAA). Overridable for tests.
     *
     * @return string[]
     */
    protected function resolveIps(string $host): array
    {
        $ips = [];

        // Try gethostbynamel for IPv4
        $ipv4List = @gethostbynamel($host);

        if (is_array($ipv4List)) {
            foreach ($ipv4List as $ip) {
                $ips[] = $ip;
            }
        }

        // Try dns_get_record for AAAA (and A for completeness)
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $rec) {
                    if (isset($rec['ip']) && filter_var($rec['ip'], FILTER_VALIDATE_IP) !== false) {
                        $ips[] = $rec['ip'];
                    }

                    if (isset($rec['ipv6']) && filter_var($rec['ipv6'], FILTER_VALIDATE_IP) !== false) {
                        $ips[] = $rec['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }

    public function isIpBlocked(string $ip): bool
    {
        $ip = trim($ip, '[]');

        if ($ip === '') {
            return false;
        }

        // Explicit metadata
        if ($ip === '169.254.169.254') {
            return true;
        }

        if ($ip === '0.0.0.0' || $ip === '::' || $ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
            return true;
        }

        // Use filter_var flags to quickly block private/reserved — if it fails, it's private/reserved
        // But we want explicit logic, so we do both.

        // IPv4 checks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($ip);

            if ($long === false) {
                return true;
            }

            // Use unsigned
            $long = sprintf('%u', $long);
            $long = (int) $long;

            // 127.0.0.0/8
            if (($long & 0xFF000000) === 0x7F000000) {
                return true;
            }

            // 10.0.0.0/8
            if (($long & 0xFF000000) === 0x0A000000) {
                return true;
            }

            // 172.16.0.0/12 => 172.16 - 172.31
            if (($long & 0xFFF00000) === 0xAC100000) {
                return true;
            }

            // 192.168.0.0/16
            if (($long & 0xFFFF0000) === 0xC0A80000) {
                return true;
            }

            // 169.254.0.0/16 link-local
            if (($long & 0xFFFF0000) === 0xA9FE0000) {
                return true;
            }

            // 0.0.0.0/8 (software)
            if (($long & 0xFF000000) === 0x00000000) {
                return true;
            }

            // Broadcast etc handled by RESERVED flag, but we already cover
        }

        // IPv6 checks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $lower = strtolower($ip);

            // Loopback
            if ($lower === '::1' || $lower === '0:0:0:0:0:0:0:1') {
                return true;
            }

            // Unspecified ::
            if ($lower === '::' || $lower === '0:0:0:0:0:0:0:0') {
                return true;
            }

            // Use inet_pton to check prefixes
            $packed = @inet_pton($ip);

            if ($packed !== false) {
                $bytes = unpack('C*', $packed);

                if ($bytes !== false) {
                    // fc00::/7 (unique local) => first 7 bits 1111110 => first byte 0xFC or 0xFD
                    $first = $bytes[1];

                    if (($first & 0xFE) === 0xFC) {
                        return true;
                    }

                    // fe80::/10 link-local => 1111111010 => first byte 0xFE, second byte 0x80-BF masked 0xC0 == 0x80
                    if ($first === 0xFE && isset($bytes[2]) && ($bytes[2] & 0xC0) === 0x80) {
                        return true;
                    }

                    // ff00::/8 multicast - treat as blocked? Spec says block link-local but multicast also private
                    // Be conservative: block multicast
                    if ($first === 0xFF) {
                        return true;
                    }
                }
            }

            // Fallback string prefix checks for quick
            if (str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd')) {
                return true;
            }

            if (str_starts_with($lower, 'fe80:')) {
                return true;
            }
        }

        // Also use filter's flags as extra safety: if filter says it's private/reserved, block
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            // If IP is valid but fails NO_PRIV filter, it's private/reserved
            // But we already handled most; return true for any remaining
            // Need to avoid blocking public IPs that are not private: filter returns false for private/reserved
            // So if we are here and IP was valid, and filter says false, block it.
            // To avoid double, just return true
            // But ensure we don't block public IPs that passed earlier checks: public IPs would pass filter and return IP string, not false.
            // So this check would not trigger for public IPs.
            // For safety, if IP is valid and not explicitly allowed above, check via filter.

            // Re-check with filter to be safe: only block if filter says blocked
            // Since we already passed IP validation, if filter fails, block
            return true;
        }

        return false;
    }

    /**
     * Resolve redirect Location against base URL.
     */
    protected function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        // Absolute URL
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        // Protocol-relative
        if (str_starts_with($location, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?? 'https';

            return $scheme.':'.$location;
        }

        // Absolute path
        if (str_starts_with($location, '/')) {
            $parts = parse_url($baseUrl);

            if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
                return null;
            }

            $port = isset($parts['port']) ? ':'.$parts['port'] : '';

            return $parts['scheme'].'://'.$parts['host'].$port.$location;
        }

        // Relative path
        $parts = parse_url($baseUrl);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        // Remove last segment after /
        $dir = substr($path, 0, strrpos($path, '/') + 1);

        return $parts['scheme'].'://'.$parts['host'].$port.$dir.$location;
    }

    /**
     * @param  string[]  $allowed
     */
    protected function isAllowedContentType(string $contentType, array $allowed): bool
    {
        foreach ($allowed as $allowedType) {
            $allowedType = strtolower(trim((string) $allowedType));

            if ($allowedType !== '' && str_starts_with($contentType, $allowedType)) {
                return true;
            }

            // Allow wildcard text/* handling if config contains text/*?
            // But our default is explicit; handle prefix already
        }

        return false;
    }

    protected function extractTitle(string $body): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m) === 1) {
            $title = trim((string) $m[1]);
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = mb_substr($title, 0, 500);

            return $title !== '' ? $title : null;
        }

        return null;
    }

    protected function redactUrl(string $url): string
    {
        // Redact query for logging safety (keep domain + path)
        $parts = parse_url($url);

        if ($parts === false) {
            return '[redacted]';
        }

        $redacted = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '[host]');

        if (isset($parts['port'])) {
            $redacted .= ':'.$parts['port'];
        }

        $redacted .= $parts['path'] ?? '/';

        return $redacted;
    }

    protected function logBlocked(string $url, string $code): void
    {
        Log::warning('Safe fetch blocked', [
            'url' => $this->redactUrl($url),
            'code' => $code,
        ]);
    }
}
