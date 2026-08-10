<?php

use App\Models\CandidateProfile;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'delete');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->otherUser = User::factory()->create();
    $this->otherProfile = CandidateProfile::factory()->create(['user_id' => $this->otherUser->id]);
});

it('returns 401 for unauthenticated request', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $this->deleteJson("/api/v1/resumes/{$resume->id}")
        ->assertStatus(401);
});

it('deletes the authenticated users own draft resume', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/resumes/{$resume->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('resumes', ['id' => $resume->id]);
});

it('returns 404 on subsequent show after deletion', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/resumes/{$resume->id}")
        ->assertStatus(204);

    $this->actingAs($this->user)
        ->getJson("/api/v1/resumes/{$resume->id}")
        ->assertStatus(404);
});

it('returns 404 when trying to delete another users resume', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->otherProfile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/resumes/{$resume->id}");

    $response->assertStatus(404);
    $this->assertDatabaseHas('resumes', ['id' => $resume->id]);
});

it('returns 403 when trying to delete an approved resume', function () {
    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/resumes/{$resume->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('resumes', ['id' => $resume->id]);
});
