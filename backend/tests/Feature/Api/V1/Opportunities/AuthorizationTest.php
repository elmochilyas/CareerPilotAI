<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'auth', 'bola');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

it('returns 404 for cross-user ingestion view', function () {
    $ingestion = JobOpportunityIngestion::factory()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$ingestion->id}");

    $response->assertStatus(404);
});

it('returns 404 for cross-user ingestion retry', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/retry");

    $response->assertStatus(404);
});

it('returns 404 for cross-user ingestion delete', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/opportunities/ingestions/{$ingestion->id}");

    $response->assertStatus(404);
});

it('returns 404 for cross-user ingestion source', function () {
    $ingestion = JobOpportunityIngestion::factory()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$ingestion->id}/source");

    $response->assertStatus(404);
});

it('returns 404 for cross-user suggestions', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->otherUser->id,
    ]);
    $suggestion = JobOpportunitySuggestion::factory()->create([
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$ingestion->id}/suggestions");

    $response->assertStatus(404);
});

it('returns 404 for cross-user suggestion review', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->otherUser->id,
    ]);
    $suggestion = JobOpportunitySuggestion::factory()->create([
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$ingestion->id}/suggestions/{$suggestion->id}", [
            'decision' => 'accepted',
        ]);

    $response->assertStatus(404);
});

it('returns 404 for cross-user preview', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");

    $response->assertStatus(404);
});

it('returns 404 for cross-user confirm', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm");

    $response->assertStatus(404);
});

it('shows an owned opportunity when its company is null', function () {
    $profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $profile->id,
        'company_id' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$opportunity->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $opportunity->id)
        ->assertJsonPath('data.company', null);
});

it('returns 404 for a cross-user opportunity detail', function () {
    $otherProfile = CandidateProfile::factory()->create(['user_id' => $this->otherUser->id]);
    $opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $otherProfile->id,
        'company_id' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$opportunity->id}");

    $response->assertNotFound();
});
