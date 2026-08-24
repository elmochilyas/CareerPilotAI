<?php

use App\Models\CandidateProfile;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\ProfileItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'possible-duplicate');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->document = CvDocument::factory()->readyForReview()->create(['user_id' => $this->user->id]);
    $this->run = CvProcessingRun::factory()->create(['cv_document_id' => $this->document->id]);
});

it('flags 3LM Solutions vs 3LM Solution as possible duplicate', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'start_date' => '2024-01-01',
        'end_date' => null,
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'suggested_value' => [
            'title' => 'Backend Developer',
            'organization' => '3LM Solution',
            'start_date' => '2024-02-01',
            'end_date' => null,
            'is_current' => true,
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(1);
    expect($preview->json('data.conflicts.0.type'))->toBe('possible_duplicate');
    expect($preview->json('data.conflicts.0.similarity'))->toBeGreaterThan(0.85);
});

it('flags Backend Developer vs Backend Developer Intern at same company with overlapping dates as possible duplicate', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme Corp',
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'suggested_value' => [
            'title' => 'Backend Developer Intern',
            'organization' => 'Acme Corp',
            'start_date' => '2024-06-01',
            'end_date' => '2024-12-31',
            'is_current' => false,
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(1);
    expect($preview->json('data.conflicts.0.type'))->toBe('possible_duplicate');
});

it('does not flag Backend Developer vs Frontend Developer as possible duplicate', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme Corp',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'suggested_value' => [
            'title' => 'Frontend Developer',
            'organization' => 'Acme Corp',
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(0);
});

it('allows same role/company with non-overlapping dates 2020-2021 vs 2024-2025 as separate', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => '2020-01-01',
        'end_date' => '2021-12-31',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'suggested_value' => [
            'title' => 'Backend Developer',
            'organization' => 'Acme',
            'start_date' => '2024-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    // Should be no duplicate nor possible (gap >180)
    expect($preview->json('data.conflicts'))->toHaveCount(0);

    $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ])->assertStatus(200);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(2);
});

it('flags project names with minor typo as possible duplicate', function () {
    ProfileItem::factory()->project()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'LaraSkills',
        'organization' => null,
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'project',
        'suggested_value' => [
            'name' => 'LaraSkill',
            'role' => 'Full Stack',
            'description' => 'typo',
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts.0.type'))->toBe('possible_duplicate');
});

it('flags education institution minor variation as possible duplicate', function () {
    ProfileItem::factory()->education()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Bachelor of Science',
        'organization' => 'University of Casablanca',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'education',
        'suggested_value' => [
            'degree' => 'Bachelor of Science',
            'institution' => 'University of Casablanca Faculty of Science',
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(1);
    expect($preview->json('data.conflicts.0.type'))->toBe('possible_duplicate');
});

it('keeps existing when candidate chooses Keep existing on possible duplicate', function () {
    $existing = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'start_date' => '2024-01-01',
        'display_order' => 0,
    ]);

    $suggestion = CvSuggestion::factory()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'review_status' => 'keep_existing',
        'suggested_value' => [
            'title' => 'Backend Developer',
            'organization' => '3LM Solution',
            'start_date' => '2024-01-01',
        ],
    ]);

    // Preview should not report conflict when already keep_existing
    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(0);

    $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ])->assertStatus(200);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
    expect($suggestion->fresh()->import_status)->toBe('skipped');
    expect(ProfileItem::find($existing->id)->title)->toBe('Backend Developer');
});

it('updates existing when candidate chooses Update existing on possible duplicate', function () {
    $existing = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'description' => 'Old desc',
        'start_date' => '2024-01-01',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'review_status' => 'update_existing',
        'suggested_value' => [
            'title' => 'Backend Developer',
            'organization' => '3LM Solution',
            'description' => 'New desc from CV',
            'start_date' => '2024-01-01',
        ],
    ]);

    $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ])->assertStatus(200);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
    expect(ProfileItem::find($existing->id)->fresh()->description)->toBe('New desc from CV');
});

it('creates separately when candidate chooses Create separately on possible duplicate', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'review_status' => 'create_new',
        'suggested_value' => [
            'title' => 'Backend Developer',
            'organization' => '3LM Solution',
            'start_date' => '2024-01-01',
        ],
    ]);

    // Preview should not report conflict when create_new chosen (explicit)
    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(0);

    $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ])->assertStatus(200);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(2);
});

it('preserves decision on refresh/re-preview', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'display_order' => 0,
    ]);

    $suggestion = CvSuggestion::factory()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'review_status' => 'pending',
        'suggested_value' => [
            'title' => 'Backend Developer',
            'organization' => '3LM Solution',
            'start_date' => '2024-01-01',
        ],
    ]);

    // User chooses keep_existing (from pending)
    $this->actingAs($this->user)->patchJson("/api/v1/cv/{$this->document->id}/suggestions/{$suggestion->id}", [
        'decision' => 'keep_existing',
    ])->assertStatus(200);

    // Preview should now have 0 conflicts (resolved via keep_existing)
    $secondPreview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $secondPreview->assertStatus(200);
    expect($secondPreview->json('data.conflicts'))->toHaveCount(0);

    // Apply should respect keep_existing -> no new row
    $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ])->assertStatus(200);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
});

it('preview and apply remain consistent for possible duplicate', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'review_status' => 'accepted',
        'suggested_value' => [
            'title' => 'Backend Developer Intern',
            'organization' => 'Acme',
            'start_date' => '2024-06-01',
            'end_date' => '2024-12-31',
        ],
    ]);
    CvSuggestion::factory()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'review_status' => 'accepted',
        'suggested_value' => [
            'title' => 'Frontend Developer',
            'organization' => 'Acme',
            'start_date' => '2024-06-01',
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    $conflicts = $preview->json('data.conflicts');
    // One possible duplicate (Backend Intern), Frontend should be separate
    expect($conflicts)->toHaveCount(1);
    expect($conflicts[0]['type'])->toBe('possible_duplicate');

    // Apply with accepted (which for possible duplicate means create separately per our logic: accepted => create)
    // But we haven't given explicit create_new, so our Apply will treat accepted as create separately for possible duplicate
    // That means it will create 2 new items (Backend Intern as separate, Frontend as separate) -> total 3
    // However if we want strict require explicit, it would skip. Our implementation creates for accepted.
    // For this test, we expect consistent: preview shows 1 possible, apply should create 1 extra for Frontend + 1 for Intern (since accepted => create) = 2 new, no skip
    // But if we change to keep_existing for Intern, preview would have 0 conflicts and apply would skip. That's tested elsewhere.

    // For now verify that Apply does not silently merge Frontend (separate) and handles Intern as per decision
    // Since decision is accepted for both, Intern will be created separately (not merged), Frontend created
    $apply = $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ]);
    $apply->assertStatus(200);
    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(3);
    expect($apply->json('data.summary.items_created'))->toBe(2);
});
