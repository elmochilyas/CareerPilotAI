<?php

use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'skills', 'completion');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id, 'profile_completion' => 80]);
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->skill = Skill::factory()->create();
});

it('profile completion is unchanged after creating a skill', function () {
    $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ]);

    $this->profile->refresh();
    expect((float) $this->profile->profile_completion)->toBe(80.0);
});

it('profile completion is unchanged after deleting a skill', function () {
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $this->withToken($this->token)->deleteJson("/api/v1/candidate/skills/{$cs['id']}");

    $this->profile->refresh();
    expect((float) $this->profile->profile_completion)->toBe(80.0);
});
