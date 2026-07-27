<?php

use App\Jobs\ProcessJobIngestionJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'ingestion');

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
});

it('creates an ingestion with valid description', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => 'We are looking for a Senior Laravel Developer with at least 5 years of experience in PHP and MySQL. The ideal candidate will join our team in Casablanca.',
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => ['id', 'status', 'created_at'],
    ]);
    expect($response->json('data.status'))->toBe('draft');
});

it('rejects description that is too short', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => 'Too short',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('source_description');
});

it('rejects unauthenticated request', function () {
    $response = $this->postJson('/api/v1/opportunities/ingestions', [
        'source_description' => 'Senior Laravel Developer position with experience requirements.',
    ]);

    $response->assertStatus(401);
});

it('accepts optional source URL', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => 'We are hiring a Vue.js Frontend Developer for our team in Casablanca. Minimum 3 years of experience.',
            'source_url' => 'https://example.com/jobs/123',
        ]);

    $response->assertStatus(201);
});

it('rejects invalid source URL', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => 'We are hiring a Vue.js Frontend Developer for our team in Casablanca. Minimum 3 years of experience.',
            'source_url' => 'ftp://invalid.com/job',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('source_url');
});

it('accepts optional personal label', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => 'DevOps Engineer needed for cloud infrastructure management with AWS experience.',
            'personal_label' => 'Interesting DevOps role',
        ]);

    $response->assertStatus(201);
    expect($response->json('data.status'))->toBe('draft');
});

it('detects duplicate ingestion by content hash', function () {
    $description = 'Senior Laravel Developer job at TechCorp. We are looking for an experienced developer with at least 5 years of experience.';

    $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => $description,
        ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => $description,
        ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('duplicate_ingestion');
    expect($response->json('errors.existing_ingestion_id'))->toBeInt();
});

it('returns a safe duplicate response for a cancelled ingestion', function () {
    $description = 'Backend Engineer role requiring Laravel, MySQL, API design, testing, and collaborative product delivery experience.';

    $created = $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => $description,
        ]);

    $ingestionId = $created->json('data.id');

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/opportunities/ingestions/{$ingestionId}")
        ->assertOk();

    Queue::fake();

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => $description,
        ]);

    $response->assertStatus(409)
        ->assertJson([
            'code' => 'duplicate_ingestion',
            'detail' => 'This job description has already been imported.',
            'errors' => [
                'existing_ingestion_id' => $ingestionId,
                'existing_status' => 'cancelled',
            ],
        ]);

    expect($response->getContent())
        ->not->toContain('SQLSTATE')
        ->not->toContain('Integrity constraint violation');

    Queue::assertNothingPushed();
});

it('allows same description for different users', function () {
    $otherUser = User::factory()->create();
    $description = 'Backend Engineer job description with Laravel and API development experience.';

    $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => $description,
        ]);

    $response = $this->actingAs($otherUser)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => $description,
        ]);

    $response->assertStatus(201);
});

it('dispatches process job on creation', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/opportunities/ingestions', [
            'source_description' => 'Full Stack Developer position with Laravel and Vue.js experience required for our Casablanca office.',
        ]);

    Queue::assertPushed(ProcessJobIngestionJob::class);
});
