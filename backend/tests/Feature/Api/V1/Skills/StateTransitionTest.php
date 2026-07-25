<?php

use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class)->group('api', 'skills', 'transitions');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->skill = Skill::factory()->create();
});

function createSkill($token, $skill, $state, $proficiency = 'intermediate'): array
{
    $response = test()->withToken($token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id,
        'state' => $state,
        'proficiency_level' => $proficiency,
    ]);

    return $response->json('data');
}

function updateSkillState($token, $id, $state): TestResponse
{
    return test()->withToken($token)->patchJson("/api/v1/candidate/skills/{$id}", [
        'state' => $state,
    ]);
}

it('transitions claimed to verified with evidence', function () {
    $cs = createSkill($this->token, $this->skill, 'claimed');
    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);
    $response = updateSkillState($this->token, $cs['id'], 'verified');
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('verified');
});

it('rejects claimed to verified without evidence', function () {
    $cs = createSkill($this->token, $this->skill, 'claimed');
    $response = updateSkillState($this->token, $cs['id'], 'verified');
    $response->assertStatus(422);
    expect($response->json('code'))->toBe('skill_verification_requirements_not_met');
});

it('transitions claimed to learning', function () {
    $cs = createSkill($this->token, $this->skill, 'claimed');
    $response = updateSkillState($this->token, $cs['id'], 'learning');
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('learning');
});

it('transitions claimed to archived', function () {
    $cs = createSkill($this->token, $this->skill, 'claimed');
    $response = test()->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive");
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('archived');
});

it('transitions verified to claimed', function () {
    $cs = createSkill($this->token, $this->skill, 'claimed');
    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);
    updateSkillState($this->token, $cs['id'], 'verified');
    $response = updateSkillState($this->token, $cs['id'], 'claimed');
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('claimed');
});

it('transitions verified to archived', function () {
    $cs = createSkill($this->token, $this->skill, 'claimed');
    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);
    updateSkillState($this->token, $cs['id'], 'verified');
    $response = test()->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/archive");
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('archived');
});

it('transitions learning to verified with evidence', function () {
    $cs = createSkill($this->token, $this->skill, 'learning');
    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);
    $response = updateSkillState($this->token, $cs['id'], 'verified');
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('verified');
});

it('transitions claimed to rejected', function () {
    $cs = createSkill($this->token, $this->skill, 'claimed');
    $response = updateSkillState($this->token, $cs['id'], 'rejected');
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('rejected');
});

it('transitions verified to learning', function () {
    $cs = createSkill($this->token, $this->skill, 'claimed');
    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);
    updateSkillState($this->token, $cs['id'], 'verified');
    $response = updateSkillState($this->token, $cs['id'], 'learning');
    $response->assertStatus(200);
    expect($response->json('data.state'))->toBe('learning');
});
