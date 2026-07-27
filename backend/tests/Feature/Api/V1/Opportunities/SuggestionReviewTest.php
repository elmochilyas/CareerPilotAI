<?php

use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'suggestions');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    $this->suggestion = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
    ]);
});

it('lists suggestions for an ingestion', function () {
    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('accepts a suggestion', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'accepted',
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_decision'))->toBe('accepted');
});

it('rejects a suggestion', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'rejected',
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_decision'))->toBe('rejected');
});

it('edits a suggestion with edited value', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'edited',
            'edited_value' => ['title' => 'Modified Title'],
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_decision'))->toBe('edited');
});

it('rejects invalid decision', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'invalid_decision',
        ]);

    $response->assertStatus(422);
});

it('batch updates suggestions', function () {
    $suggestion2 = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/batch", [
            'decisions' => [
                ['id' => $this->suggestion->id, 'decision' => 'accepted'],
                ['id' => $suggestion2->id, 'decision' => 'rejected'],
            ],
        ]);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('rejects batch with invalid suggestion id', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/batch", [
            'decisions' => [
                ['id' => 99999, 'decision' => 'accepted'],
            ],
        ]);

    $response->assertStatus(422);
});

it('rejects review when ingestion is not review_ready', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);
    $suggestion = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$ingestion->id}/suggestions/{$suggestion->id}", [
            'decision' => 'accepted',
        ]);

    $response->assertStatus(409);
});
