<?php

use App\Models\CandidateProfile;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'preview');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->otherUser = User::factory()->create();
    $this->otherProfile = CandidateProfile::factory()->create(['user_id' => $this->otherUser->id]);
});

it('returns 401 for unauthenticated request', function () {
    $resume = Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $this->getJson("/api/v1/resumes/{$resume->id}/preview")
        ->assertStatus(401);
});

it('returns structured preview data for the authenticated users resume', function () {
    $resume = Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'content' => [
            'summary' => [
                'title' => 'Professional Summary',
                'items' => [
                    ['source_id' => 1, 'source_type' => 'profile', 'text' => 'Experienced developer', 'display_order' => 0],
                ],
                'display_order' => 0,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/resumes/{$resume->id}/preview");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'headline',
            'summary',
            'sections' => [
                '*' => [
                    'type',
                    'title',
                    'items' => [
                        '*' => ['source_ref', 'original_text', 'current_text', 'ai_proposals', 'metadata'],
                    ],
                    'has_changes',
                ],
            ],
        ],
    ]);
    expect($response->json('data.summary'))->toBe('Experienced developer');
});

it('returns 404 when trying to preview another users resume', function () {
    $resume = Resume::factory()->create([
        'candidate_profile_id' => $this->otherProfile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/resumes/{$resume->id}/preview");

    $response->assertStatus(404);
});

it('returns 404 for non-existent resume', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/resumes/999999/preview');

    $response->assertStatus(404);
});
