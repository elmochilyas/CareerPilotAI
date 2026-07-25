<?php

use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'skills', 'archive');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->skill = Skill::factory()->create();
});

it('archives claimed skill', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive");
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('archived');
});

it('restores to claimed', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive");

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/restore", [
        'state' => 'claimed',
    ]);
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('claimed');
});

it('restores to learning', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive");

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/restore", [
        'state' => 'learning',
    ]);
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('learning');
});

it('restores to verified with evidence', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);
    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive");

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/restore", [
        'state' => 'verified',
    ]);
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('verified');
});

it('rejects restore to verified without evidence', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive");

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/restore", [
        'state' => 'verified',
    ]);
    $response->assertStatus(422);
    expect($response->json('code'))->toBe('skill_verification_requirements_not_met');
});

it('rejects restore with stale updated_at', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive");

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/restore", [
        'state' => 'claimed',
        'updated_at' => '2020-01-01T00:00:00Z',
    ]);
    $response->assertStatus(409);
    expect($response->json('code'))->toBe('candidate_skill_conflict');
});
