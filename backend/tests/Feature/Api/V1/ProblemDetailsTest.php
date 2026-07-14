<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class)->group('api', 'errors');

it('returns 404 problem detail for non-existent route', function () {
    $response = $this->getJson('/api/v1/non-existent');

    $response->assertStatus(404);
    $response->assertJsonStructure([
        'type',
        'title',
        'status',
        'detail',
        'instance',
        'code',
        'errors',
        'request_id',
    ]);
    expect($response->json('code'))->toBe('not_found');
    expect($response->json('status'))->toBe(404);
    expect($response->json('request_id'))->toBeString()->not->toBeEmpty();
});

it('does not expose stack trace in production mode', function () {
    app()['config']->set('app.debug', false);

    $response = $this->getJson('/api/v1/non-existent');

    $response->assertStatus(404);
    expect($response->json('debug'))->toBeNull();
});

it('includes request_id in error responses', function () {
    $requestId = 'req-test-error-456';

    $response = $this->withHeaders(['X-Request-ID' => $requestId])
        ->getJson('/api/v1/non-existent');

    $response->assertStatus(404);
    expect($response->json('request_id'))->toBe($requestId);
});

it('handles method not allowed gracefully', function () {
    $response = $this->postJson('/api/v1/health');

    $response->assertStatus(405);
    expect($response->json('code'))->toBe('method_not_allowed');
    expect($response->json('request_id'))->toBeString()->not->toBeEmpty();
});

it('returns generic 500 without internals in production mode', function () {
    app()['config']->set('app.debug', false);

    Route::get('api/v1/_test-crash', function (): never {
        throw new RuntimeException('Hidden internal error');
    });

    $response = $this->getJson('/api/v1/_test-crash');

    $response->assertStatus(500);
    expect($response->json('code'))->toBe('internal_error');
    expect($response->json('detail'))->toBe('An unexpected error occurred.');
    expect($response->json('debug'))->toBeNull();
});

it('includes debug in 500 when APP_DEBUG is true', function () {
    app()['config']->set('app.debug', true);

    Route::get('api/v1/_test-debug', function (): never {
        throw new RuntimeException('Debug test error');
    });

    $response = $this->getJson('/api/v1/_test-debug');

    $response->assertStatus(500);
    expect($response->json('code'))->toBe('internal_error');
    expect($response->json('detail'))->toBe('Debug test error');
    expect($response->json('debug.exception'))->toBe(RuntimeException::class);
});
