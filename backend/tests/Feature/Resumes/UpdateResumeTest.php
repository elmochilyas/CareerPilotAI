<?php

use App\Models\CandidateProfile;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'update');

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

    $this->putJson("/api/v1/resumes/{$resume->id}", [
        'content' => ['sections' => []],
    ])->assertStatus(401);
});

it('updates the authenticated users own draft resume', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $content = [
        'sections' => [
            [
                'type' => 'experience',
                'title' => 'Work Experience',
                'items' => [
                    [
                        'source_type' => 'experience',
                        'source_id' => '1',
                        'tailored_text' => 'Built scalable APIs using Laravel.',
                        'relevance' => 'high',
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/resumes/{$resume->id}", ['content' => $content]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', $resume->id);
    $response->assertJsonPath('data.version_no', 2);
});

it('increments version number on update', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
        'version_no' => 1,
    ]);

    $content = [
        'sections' => [
            [
                'type' => 'skills',
                'title' => 'Skills',
                'items' => [
                    [
                        'source_type' => 'skill',
                        'source_id' => '1',
                        'tailored_text' => 'PHP, Laravel, MySQL',
                        'relevance' => 'high',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($this->user)
        ->putJson("/api/v1/resumes/{$resume->id}", ['content' => $content])
        ->assertStatus(200);

    $this->actingAs($this->user)
        ->putJson("/api/v1/resumes/{$resume->id}", ['content' => $content])
        ->assertStatus(200);

    $resume->refresh();
    expect($resume->version_no)->toBe(3);
});

it('returns 403 when trying to update an approved resume', function () {
    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $content = [
        'sections' => [
            [
                'type' => 'experience',
                'title' => 'Work Experience',
                'items' => [
                    [
                        'source_type' => 'experience',
                        'source_id' => '1',
                        'tailored_text' => 'Updated text.',
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

it('returns 404 when trying to update another users resume', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->otherProfile->id,
    ]);

    $content = [
        'sections' => [
            [
                'type' => 'experience',
                'title' => 'Work Experience',
                'items' => [
                    [
                        'source_type' => 'experience',
                        'source_id' => '1',
                        'tailored_text' => 'Hacked text.',
                        'relevance' => 'high',
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/resumes/{$resume->id}", ['content' => $content]);

    $response->assertStatus(404);
});

it('returns 422 for invalid content structure', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/api/v1/resumes/{$resume->id}", [
            'content' => 'not-an-array',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('content');
});
