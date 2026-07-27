<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\ThrottleRequests;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'confirm');

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->user = User::factory()->create();
    CandidateProfile::factory()->create(['user_id' => $this->user->id]);
});

it('previews a review_ready ingestion with all decisions made', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->accepted()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'job_title',
        'extracted_value' => ['title' => 'Senior Laravel Developer'],
    ]);
    JobOpportunitySuggestion::factory()->rejected()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'company',
        'extracted_value' => ['company_name' => 'TechCorp'],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");

    $response->assertStatus(200);
    expect($response->json('data.data'))->toHaveKey('overview');
});

it('rejects preview when suggestions are pending', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");

    $response->assertStatus(409);
});

it('confirms a review_ready ingestion with all decisions made', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->accepted()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'job_title',
        'extracted_value' => ['title' => 'Senior Laravel Developer'],
    ]);
    JobOpportunitySuggestion::factory()->rejected()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'company',
        'extracted_value' => ['company_name' => 'TechCorp'],
    ]);

    $preview = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");
    $preview->assertStatus(200);
    $versionToken = $preview->json('data.version_token');

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => $versionToken,
        ]);

    $response->assertStatus(200);
    expect($response->json('data.title'))->toBe('Senior Laravel Developer');

    $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$ingestion->id}")
        ->assertOk()
        ->assertJsonPath('data.confirmed_opportunity_id', $response->json('data.id'));
});

it('rejects confirm when ingestion is not review_ready', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => (string) $ingestion->version,
        ]);

    $response->assertStatus(409);
});

it('rejects confirm when suggestions are pending', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => (string) $ingestion->version,
        ]);

    $response->assertStatus(409);
});

it('is idempotent on repeated confirm', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->accepted()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'job_title',
        'extracted_value' => ['title' => 'Senior Laravel Developer'],
    ]);

    $preview = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");
    $versionToken = $preview->json('data.version_token');

    $firstResponse = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => $versionToken,
        ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => $versionToken,
        ]);

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($firstResponse->json('data.id'));
    $this->assertDatabaseCount('job_opportunities', 1);
});
