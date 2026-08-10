<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('throttle:2,1')->get('/api/v1/_tests/throttle/numeric', fn () => response()->json([
        'status' => 'ok',
    ]));

    RateLimiter::for('test-named-throttle', fn (Request $request): Limit => Limit::perMinute(2)
        ->by($request->ip()));

    Route::middleware('throttle:test-named-throttle')->get('/api/v1/_tests/throttle/named', fn () => response()->json([
        'status' => 'ok',
    ]));
});

it('bypasses numeric and named route throttles when disabled', function (string $path, string $ip): void {
    config()->set('rate-limiting.enabled', false);

    foreach (range(1, 4) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson($path)
            ->assertOk();
    }
})->with([
    'numeric limiter' => ['/api/v1/_tests/throttle/numeric', '198.51.100.10'],
    'named limiter' => ['/api/v1/_tests/throttle/named', '198.51.100.11'],
]);

it('enforces route throttles when enabled', function (string $path, string $ip): void {
    config()->set('rate-limiting.enabled', true);

    $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson($path)->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson($path)->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson($path)
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests');
})->with([
    'numeric limiter' => ['/api/v1/_tests/throttle/numeric', '198.51.100.20'],
    'named limiter' => ['/api/v1/_tests/throttle/named', '198.51.100.21'],
]);

it('enables route throttling by default in the test environment', function (): void {
    expect(app()->environment())->toBe('testing')
        ->and(config('rate-limiting.enabled'))->toBeTrue();
});
