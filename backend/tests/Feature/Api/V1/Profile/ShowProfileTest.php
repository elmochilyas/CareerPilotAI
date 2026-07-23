<?php

use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'profile');

it('returns an empty profile resource without persisting it', function () {
    $user = User::factory()->create(['full_name' => 'Jane Doe']);
    $response = $this->actingAs($user)->getJson('/api/v1/profile');
    $response->assertOk()->assertJsonPath('data.full_name', 'Jane Doe')->assertJsonPath('data.profile_completion', 0)
        ->assertJsonStructure(['data' => ['completion_details' => ['areas', 'missing_areas'], 'items' => ['education', 'experience', 'project', 'certification']]]);
    expect($user->candidateProfile()->exists())->toBeFalse();
});

it('returns an existing profile with grouped items', function () {
    $profile = CandidateProfile::factory()->create();
    ProfileItem::factory()->education()->for($profile)->create();
    $this->actingAs($profile->user)->getJson('/api/v1/profile')->assertOk()->assertJsonCount(1, 'data.items.education')->assertJsonCount(0, 'data.items.project');
});

it('requires authentication to view a profile', function () {
    $this->getJson('/api/v1/profile')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});
