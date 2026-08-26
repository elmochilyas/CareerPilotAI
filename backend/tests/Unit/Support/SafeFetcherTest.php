<?php

use App\Support\SafeWebFetch\SafeFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class)->group('safe-fetch');

// Helper to make fetcher with stubbed DNS
function makeFetcherWithDns(array $dnsMap): SafeFetcher
{
    return new class($dnsMap) extends SafeFetcher
    {
        public function __construct(private array $map) {}

        protected function resolveIps(string $host): array
        {
            $host = strtolower($host);

            return $this->map[$host] ?? [];
        }
    };
}

it('blocks localhost', function () {
    $fetcher = makeFetcherWithDns([]);
    $result = $fetcher->fetch('http://localhost/company');
    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe('ssrf_blocked');
});

it('blocks 127.0.0.1', function () {
    $fetcher = makeFetcherWithDns([]);
    $result = $fetcher->fetch('http://127.0.0.1/page');
    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe('ssrf_blocked');
});

it('blocks ::1', function () {
    $fetcher = makeFetcherWithDns([]);
    $result = $fetcher->fetch('http://[::1]/page');
    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe('ssrf_blocked');
});

it('blocks metadata endpoint', function () {
    $fetcher = makeFetcherWithDns([]);
    $result = $fetcher->fetch('http://169.254.169.254/latest/meta-data/');
    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe('ssrf_blocked');
});

it('blocks RFC1918 ranges', function () {
    $fetcher = makeFetcherWithDns([]);
    expect($fetcher->fetch('http://192.168.1.10/internal')->errorCode)->toBe('ssrf_blocked')
        ->and($fetcher->fetch('http://10.0.0.5/')->errorCode)->toBe('ssrf_blocked')
        ->and($fetcher->fetch('http://172.16.5.1/')->errorCode)->toBe('ssrf_blocked');
});

it('blocks link-local', function () {
    $fetcher = makeFetcherWithDns([]);
    $result = $fetcher->fetch('http://169.254.10.20/resource');
    expect($result->errorCode)->toBe('ssrf_blocked');
});

it('blocks private IP via DNS resolution', function () {
    $fetcher = makeFetcherWithDns(['evil.example.com' => ['10.0.0.5']]);
    $result = $fetcher->fetch('http://evil.example.com/page');
    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe('ssrf_blocked');
});

it('blocks redirect to private IP', function () {
    Http::fake([
        'example.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
    ]);

    $fetcher = makeFetcherWithDns([
        'example.com' => ['93.184.216.34'],
        '127.0.0.1' => ['127.0.0.1'],
    ]);

    $result = $fetcher->fetch('https://example.com/redirect');
    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe('redirect_blocked');
});

it('rejects too many redirects', function () {
    Http::fake([
        '*' => Http::response('', 302, ['Location' => 'https://example.com/loop']),
    ]);

    $fetcher = makeFetcherWithDns(['example.com' => ['93.184.216.34']]);
    Config::set('safe-fetch.max_redirects', 3);

    $result = $fetcher->fetch('https://example.com/start');
    expect($result->errorCode)->toBe('too_many_redirects');
});

it('rejects oversized response', function () {
    Config::set('safe-fetch.max_bytes', 10);
    Http::fake([
        'example.com/*' => Http::response(str_repeat('a', 100), 200, ['Content-Type' => 'text/html']),
    ]);

    $fetcher = makeFetcherWithDns(['example.com' => ['93.184.216.34']]);
    $result = $fetcher->fetch('https://example.com/large');
    expect($result->errorCode)->toBe('response_too_large');
});

it('rejects unsupported content type', function () {
    Http::fake([
        'example.com/*' => Http::response('binary', 200, ['Content-Type' => 'application/zip']),
    ]);

    $fetcher = makeFetcherWithDns(['example.com' => ['93.184.216.34']]);
    $result = $fetcher->fetch('https://example.com/file.zip');
    expect($result->errorCode)->toBe('unsupported_content_type');
});

it('denies file and ftp schemes', function () {
    $fetcher = makeFetcherWithDns([]);
    expect($fetcher->fetch('file:///etc/passwd')->errorCode)->toBe('unsupported_scheme')
        ->and($fetcher->fetch('ftp://example.com/file')->errorCode)->toBe('unsupported_scheme');
});

it('handles timeout as failure', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out.');
    });

    $fetcher = makeFetcherWithDns(['example.com' => ['93.184.216.34']]);
    $result = $fetcher->fetch('https://example.com/timeout');
    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe('fetch_timeout');
});

it('does not throw on malformed html', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>Test</title><body><p>unclosed', 200, ['Content-Type' => 'text/html']),
    ]);

    $fetcher = makeFetcherWithDns(['example.com' => ['93.184.216.34']]);
    $result = $fetcher->fetch('https://example.com/broken');
    expect($result->ok)->toBeTrue()
        ->and($result->title)->toBe('Test');
});

it('allows public url', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><title>OK</title><body>Hello</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $fetcher = makeFetcherWithDns(['example.com' => ['93.184.216.34']]);
    $result = $fetcher->fetch('https://example.com/page');
    expect($result->ok)->toBeTrue()
        ->and($result->finalUrl)->toBe('https://example.com/page');
});
