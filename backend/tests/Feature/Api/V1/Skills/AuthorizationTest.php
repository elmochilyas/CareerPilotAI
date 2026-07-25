<?php

use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'skills', 'authz');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->token = $this->user->createToken('test')->plainTextToken;

    $this->otherUser = User::factory()->create();
    $this->otherProfile = CandidateProfile::factory()->create(['user_id' => $this->otherUser->id]);
    $this->otherToken = $this->otherUser->createToken('test')->plainTextToken;

    $this->skill = Skill::factory()->create();
    $this->otherSkill = CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->otherProfile->id,
        'skill_id' => $this->skill->id,
    ]);
});

it('returns 404 for cross-user show', function () {
    $response = $this->withToken($this->token)
        ->getJson("/api/v1/candidate/skills/{$this->otherSkill->id}");

    $response->assertStatus(404);
});

it('returns 404 for cross-user update', function () {
    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/candidate/skills/{$this->otherSkill->id}", [
            'proficiency_level' => 'advanced',
        ]);

    $response->assertStatus(404);
});

it('returns 404 for cross-user archive', function () {
    $response = $this->withToken($this->token)
        ->postJson("/api/v1/candidate/skills/{$this->otherSkill->id}/archive");

    $response->assertStatus(404);
});

it('returns 404 for cross-user restore', function () {
    $response = $this->withToken($this->token)
        ->postJson("/api/v1/candidate/skills/{$this->otherSkill->id}/restore", [
            'state' => 'claimed',
        ]);

    $response->assertStatus(404);
});

it('returns 404 for cross-user delete', function () {
    $response = $this->withToken($this->token)
        ->deleteJson("/api/v1/candidate/skills/{$this->otherSkill->id}");

    $response->assertStatus(404);
});

it('returns 404 for cross-user evidence', function () {
    $response = $this->withToken($this->token)
        ->postJson("/api/v1/candidate/skills/{$this->otherSkill->id}/evidence", [
            'type' => 'url', 'value' => 'https://example.com',
        ]);

    $response->assertStatus(404);
});
