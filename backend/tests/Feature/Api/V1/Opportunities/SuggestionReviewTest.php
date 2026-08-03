<?php

use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\Skill;
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
        'type' => 'job_title',
        'group_key' => 'overview',
        'field' => 'title',
        'extracted_value' => ['value' => 'Backend Developer'],
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
            'version' => $this->suggestion->version,
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_decision'))->toBe('accepted');
});

it('rejects a suggestion', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'rejected',
            'version' => $this->suggestion->version,
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_decision'))->toBe('rejected');
});

it('edits a suggestion with edited value', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'edited',
            'edited_value' => ['value' => 'Modified Title'],
            'version' => $this->suggestion->version,
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_decision'))->toBe('edited');
});

it('rejects invalid decision', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'invalid_decision',
            'version' => $this->suggestion->version,
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
                [
                    'id' => $this->suggestion->id,
                    'decision' => 'accepted',
                    'version' => $this->suggestion->version,
                ],
                [
                    'id' => $suggestion2->id,
                    'decision' => 'rejected',
                    'version' => $suggestion2->version,
                ],
            ],
        ]);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('rejects batch with invalid suggestion id', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/batch", [
            'decisions' => [
                ['id' => 99999, 'decision' => 'accepted', 'version' => 1],
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
            'version' => $suggestion->version,
        ]);

    $response->assertStatus(409);
});

it('rejects a stale suggestion version without overwriting the current decision', function () {
    $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'accepted',
            'version' => $this->suggestion->version,
        ])
        ->assertOk();

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'rejected',
            'version' => $this->suggestion->version,
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('code', 'stale_mutation');
    $this->assertDatabaseHas('job_opportunity_suggestions', [
        'id' => $this->suggestion->id,
        'review_decision' => 'accepted',
        'version' => 2,
    ]);
});

it('rolls back every batch decision when one suggestion version is stale', function () {
    $suggestion2 = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
        'version' => 2,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/batch", [
            'decisions' => [
                [
                    'id' => $this->suggestion->id,
                    'decision' => 'accepted',
                    'version' => $this->suggestion->version,
                ],
                [
                    'id' => $suggestion2->id,
                    'decision' => 'rejected',
                    'version' => 1,
                ],
            ],
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('code', 'stale_mutation');
    $this->assertDatabaseHas('job_opportunity_suggestions', [
        'id' => $this->suggestion->id,
        'review_decision' => 'pending',
        'version' => 1,
    ]);
});

it('resolves an ambiguous skill to a candidate-selected canonical skill', function () {
    $skill = Skill::factory()->create(['is_active' => true]);
    $suggestion = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
        'group_key' => 'required_skills',
        'extracted_value' => ['label' => 'Spring'],
        'resolution' => 'ambiguous',
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$suggestion->id}", [
            'decision' => 'resolved',
            'resolved_skill_id' => $skill->id,
            'version' => $suggestion->version,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.review_decision', 'resolved')
        ->assertJsonPath('data.resolution', 'candidate_resolved')
        ->assertJsonPath('data.resolved_skill_id', $skill->id);
});

it('allows an ambiguous skill to remain as an unknown original label', function () {
    $suggestion = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
        'group_key' => 'required_skills',
        'extracted_value' => ['label' => 'Internal Platform'],
        'resolution' => 'ambiguous',
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions/{$suggestion->id}", [
            'decision' => 'resolved',
            'resolved_skill_id' => null,
            'version' => $suggestion->version,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.review_decision', 'resolved')
        ->assertJsonPath('data.resolution', 'unknown')
        ->assertJsonPath('data.resolved_skill_id', null);
});

it('adds a missing responsibility as an accepted edited suggestion', function () {
    $initialVersion = $this->ingestion->version;

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions", [
            'type' => 'responsibility',
            'value' => 'Review pull requests and mentor junior developers.',
            'ingestion_version' => $initialVersion,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'responsibility')
        ->assertJsonPath('data.review_decision', 'accepted')
        ->assertJsonPath('data.extracted_value.text', 'Review pull requests and mentor junior developers.')
        ->assertJsonPath('data.edited_value.text', 'Review pull requests and mentor junior developers.');

    expect($this->ingestion->refresh()->version)->toBe($initialVersion + 1);
});

it('adds a missing skill with an unknown resolution and preserves its classification', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions", [
            'type' => 'preferred_skill',
            'value' => 'OpenTelemetry',
            'ingestion_version' => $this->ingestion->version,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'preferred_skill')
        ->assertJsonPath('data.review_decision', 'accepted')
        ->assertJsonPath('data.resolution', 'unknown')
        ->assertJsonPath('data.edited_value.label', 'OpenTelemetry');
});

it('rejects unsupported or empty manual suggestions', function (array $payload) {
    $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions", [
            ...$payload,
            'ingestion_version' => $this->ingestion->version,
        ])
        ->assertUnprocessable();
})->with([
    'unsupported type' => [['type' => 'job_title', 'value' => 'Platform Engineer']],
    'empty responsibility' => [['type' => 'responsibility', 'value' => '   ']],
]);

it('rejects a stale ingestion version when adding a manual suggestion', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$this->ingestion->id}/suggestions", [
            'type' => 'required_skill',
            'value' => 'Laravel',
            'ingestion_version' => $this->ingestion->version + 1,
        ]);

    $response->assertConflict()
        ->assertJsonPath('code', 'stale_mutation');

    $this->assertDatabaseMissing('job_opportunity_suggestions', [
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
    ]);
});
