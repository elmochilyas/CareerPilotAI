<?php

use App\Models\CandidateProfile;
use App\Models\CvDocument;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'pdf-export');

function exportableResumeContent(): array
{
    return [
        'summary' => [
            'title' => 'Summary',
            'display_order' => 0,
            'items' => [[
                'source_type' => 'candidate_profile',
                'source_id' => 1,
                'text' => 'Trusted Laravel developer profile.',
                'display_order' => 0,
                'metadata' => ['headline' => 'Laravel Developer'],
            ]],
        ],
        'skills' => [
            'title' => 'Skills',
            'display_order' => 1,
            'items' => [[
                'source_type' => 'candidate_skill',
                'source_id' => 1,
                'text' => 'Laravel',
                'display_order' => 0,
                'metadata' => null,
            ]],
        ],
    ];
}

it('downloads an owned approved resume as a pdf attachment', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $profile->id,
        'title' => 'Backend CV',
        'content' => exportableResumeContent(),
    ]);

    $response = $this->actingAs($user)->get("/api/v1/resumes/{$resume->id}/download/pdf");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="backend-cv-v1.pdf"');
    expect($response->getContent())->toStartWith('%PDF-');
});

it('rejects pdf download until the resume is approved', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $profile->id,
        'content' => exportableResumeContent(),
    ]);

    $this->actingAs($user)
        ->getJson("/api/v1/resumes/{$resume->id}/download/pdf")
        ->assertStatus(409)
        ->assertJsonPath('code', 'resume_not_approved');
});

it('hides another users resume pdf', function () {
    $owner = User::factory()->create();
    $ownerProfile = CandidateProfile::factory()->create(['user_id' => $owner->id]);
    $otherUser = User::factory()->create();
    CandidateProfile::factory()->create(['user_id' => $otherUser->id]);
    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $ownerProfile->id,
        'content' => exportableResumeContent(),
    ]);

    $this->actingAs($otherUser)
        ->get("/api/v1/resumes/{$resume->id}/download/pdf")
        ->assertNotFound();
});

it('renders the imported source template for the authenticated document preview', function () {
    $user = User::factory()->create(['full_name' => 'Source Candidate']);
    $profile = CandidateProfile::factory()->create([
        'user_id' => $user->id,
        'headline' => 'PHP Backend Developer',
    ]);
    CvDocument::factory()->create([
        'user_id' => $user->id,
        'status' => 'imported',
    ]);
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $profile->id,
        'template_key' => null,
        'content' => exportableResumeContent(),
    ]);

    $response = $this->actingAs($user)
        ->get("/api/v1/resumes/{$resume->id}/document-preview");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('data-resume-template="source-cv-classic"', false)
        ->assertSee('Source Candidate')
        ->assertSee('Laravel Developer')
        ->assertSee('class="skills-grid"', false)
        ->assertDontSee('rounded-lg');
});

it('hides another users document preview', function () {
    $owner = User::factory()->create();
    $ownerProfile = CandidateProfile::factory()->create(['user_id' => $owner->id]);
    $otherUser = User::factory()->create();
    CandidateProfile::factory()->create(['user_id' => $otherUser->id]);
    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $ownerProfile->id,
        'content' => exportableResumeContent(),
    ]);

    $this->actingAs($otherUser)
        ->get("/api/v1/resumes/{$resume->id}/document-preview")
        ->assertNotFound();
});
