<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('api', 'health');

it('returns a successful health response with correct structure', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'status',
            'timestamp',
            'services' => [
                'database',
            ],
        ],
    ]);

    $data = $response->json('data');
    expect($data['status'])->toBe('pass');
    expect($data['services']['database'])->toBe('up');
});

it('includes a request ID in the health response', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertHeader('X-Request-ID');
    expect($response->json('request_id'))->toBeString()->not->toBeEmpty();
});

it('preserves a provided X-Request-ID header', function () {
    $requestId = 'req-test-123';

    $response = $this->withHeaders(['X-Request-ID' => $requestId])
        ->getJson('/api/v1/health');

    $response->assertHeader('X-Request-ID', $requestId);
    expect($response->json('request_id'))->toBe($requestId);
});

it('generates a UUID when no X-Request-ID is provided', function () {
    $response = $this->getJson('/api/v1/health');

    $requestId = $response->headers->get('X-Request-ID');
    expect(Str::isUuid($requestId))->toBeTrue();
});
