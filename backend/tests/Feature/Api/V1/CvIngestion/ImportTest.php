<?php

use App\Models\CandidateProfile;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'import');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->document = CvDocument::factory()->readyForReview()->create([
        'user_id' => $this->user->id,
    ]);
    $this->run = CvProcessingRun::factory()->create([
        'cv_document_id' => $this->document->id,
    ]);
});

it('previews import before applying', function () {
    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'headline',
        'suggested_value' => ['value' => 'Senior Developer'],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$this->document->id}/import-preview");

    $response->assertStatus(200);
    expect($response->json('data.ready'))->toBeTrue();
});

it('rejects import preview when suggestions are pending', function () {
    CvSuggestion::factory()->pending()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$this->document->id}/import-preview");

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('not_all_reviewed');
});

it('applies import successfully', function () {
    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'headline',
        'suggested_value' => ['value' => 'Senior Laravel Developer'],
    ]);

    $idempotencyKey = hash('sha256', Str::uuid()->toString());

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('applied');

    $this->assertDatabaseHas('cv_documents', [
        'id' => $this->document->id,
        'status' => 'imported',
    ]);
});

it('rejects duplicate import with same idempotency key', function () {
    $idempotencyKey = hash('sha256', Str::uuid()->toString());

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'headline',
        'suggested_value' => ['value' => 'Senior Developer'],
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('import_already_applied');
});

it('rejects import when not all reviewed', function () {
    CvSuggestion::factory()->pending()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
            'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
        ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('not_all_reviewed');
});

it('shows import result', function () {
    $batch = CvImportBatch::factory()->applied()->create([
        'cv_document_id' => $this->document->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$this->document->id}/import-result");

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($batch->id);
});

it('rejects import when profile was modified before apply', function () {
    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'headline',
        'suggested_value' => ['value' => 'Senior Developer'],
    ]);

    $originalUpdatedAt = $this->profile->fresh()->updated_at->toISOString();

    $this->profile->touch();

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$this->document->id}/apply", [
            'profile_updated_at' => $originalUpdatedAt,
        ], [
            'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
        ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('profile_changed');
});

it('shows import skills as claimed with evidence', function () {
    $skill = Skill::factory()->create();
    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'skill',
        'suggested_value' => ['name' => $skill->name, 'proficiency_level' => 'intermediate'],
    ]);

    $idempotencyKey = hash('sha256', Str::uuid()->toString());

    $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

    $this->assertDatabaseHas('candidate_skills', [
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $skill->id,
        'state' => 'claimed',
    ]);
});
