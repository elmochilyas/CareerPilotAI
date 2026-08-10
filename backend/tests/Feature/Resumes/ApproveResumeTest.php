<?php

use App\Models\CandidateProfile;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'approve');

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

    $this->postJson("/api/v1/resumes/{$resume->id}/approve")
        ->assertStatus(401);
});

it('approves the authenticated users own draft resume', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/approve");

    $response->assertStatus(200);
    $response->assertJsonPath('data.status', 'approved');
    $response->assertJsonPath('data.id', $resume->id);
});

it('sets the approved_at timestamp on approval', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
        'approved_at' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/approve")
        ->assertStatus(200);

    $resume->refresh();
    expect($resume->approved_at)->not->toBeNull();
    expect($resume->status->value)->toBe('approved');
});

it('returns 404 when trying to approve another users resume', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->otherProfile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/approve");

    $response->assertStatus(404);
});

it('returns 403 when trying to approve an already approved resume', function () {
    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/approve");

    $response->assertStatus(403);
});

it('makes the resume immutable after approval', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/approve")
        ->assertStatus(200);

    $content = [
        'sections' => [
            [
                'type' => 'experience',
                'title' => 'Work Experience',
                'items' => [
                    [
                        'source_type' => 'experience',
                        'source_id' => '1',
                        'tailored_text' => 'Should not work.',
                        'relevance' => 'high',
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/resumes/{$resume->id}", ['content' => $content]);

    $response->assertStatus(403);
});
