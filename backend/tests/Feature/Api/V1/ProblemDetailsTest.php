<?php

use App\Exceptions\Api\UnprocessableEntityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
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

it('does not expose internals in 500 when APP_DEBUG is true', function () {
    app()['config']->set('app.debug', true);

    Route::get('api/v1/_test-debug', function (): never {
        throw new RuntimeException('Debug test error');
    });

    $response = $this->getJson('/api/v1/_test-debug');

    $response->assertStatus(500);
    expect($response->json('code'))->toBe('internal_error');
    expect($response->json('detail'))->toBe('An unexpected error occurred.');
    expect($response->json('debug'))->toBeNull();
    expect($response->getContent())->not->toContain('Debug test error');
});

it('returns 419 session_expired problem detail for csrf mismatch', function () {
    Route::post('api/v1/_test-csrf', function (): never {
        throw new TokenMismatchException('CSRF token mismatch.');
    });

    $response = $this->postJson('/api/v1/_test-csrf');

    $response->assertStatus(419);
    expect($response->json('code'))->toBe('session_expired');
    expect($response->json('status'))->toBe(419);
    expect($response->json('title'))->toBe('Session Expired');
    expect($response->json('request_id'))->toBeString()->not->toBeEmpty();
});

it('derives the problem title from the stable code without leaking the exception class', function () {
    Route::post('api/v1/_test-problem', function (): never {
        throw new UnprocessableEntityException(
            'This answer cannot produce a proposal.',
            'proposal_not_supported',
        );
    });

    $response = $this->postJson('/api/v1/_test-problem');

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('proposal_not_supported');
    expect($response->json('title'))->toBe('Proposal Not Supported');
    expect($response->json('detail'))->toBe('This answer cannot produce a proposal.');
    expect($response->getContent())->not->toContain('UnprocessableEntityException');
    expect($response->getContent())->not->toContain('ProblemDetailsException');
});

it('falls back to the code-derived title as detail when the message is empty', function () {
    Route::get('api/v1/_test-empty-detail', function (): never {
        throw new UnprocessableEntityException('', 'answer_already_exists');
    });

    $response = $this->getJson('/api/v1/_test-empty-detail');

    $response->assertStatus(422);
    expect($response->json('title'))->toBe('Answer Already Exists');
    expect($response->json('detail'))->toBe('Answer Already Exists');
    expect($response->getContent())->not->toContain('UnprocessableEntityException');
});
