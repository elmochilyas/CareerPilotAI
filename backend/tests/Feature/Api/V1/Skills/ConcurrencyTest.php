<?php

use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'skills', 'concurrency');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->skill = Skill::factory()->create();
});

it('returns 409 on update with stale updated_at', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $response = $this->withToken($this->token)->patchJson("/api/v1/candidate/skills/{$cs['id']}", [
        'proficiency_level' => 'advanced',
        'updated_at' => '2020-01-01T00:00:00Z',
    ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('candidate_skill_conflict');
});

it('returns 409 on archive with stale updated_at', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive", [
        'updated_at' => '2020-01-01T00:00:00Z',
    ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('candidate_skill_conflict');
});

it('adds evidence successfully', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);

    $response->assertStatus(200);
});
