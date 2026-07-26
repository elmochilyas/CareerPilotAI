<?php

use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'suggestions');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->document = CvDocument::factory()->readyForReview()->create([
        'user_id' => $this->user->id,
    ]);
    $this->run = CvProcessingRun::factory()->create([
        'cv_document_id' => $this->document->id,
    ]);
    $this->suggestion = CvSuggestion::factory()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
    ]);
});

it('lists suggestions for a document', function () {
    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$this->document->id}/suggestions");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('accepts a suggestion', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/cv/{$this->document->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'accepted',
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_status'))->toBe('accepted');
});

it('rejects a suggestion', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/cv/{$this->document->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'rejected',
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_status'))->toBe('rejected');
});

it('edits a suggestion', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/cv/{$this->document->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'edited',
            'edited_value' => ['value' => 'Edited Value'],
        ]);

    $response->assertStatus(200);
    expect($response->json('data.review_status'))->toBe('edited');
});

it('rejects invalid decision', function () {
    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/cv/{$this->document->id}/suggestions/{$this->suggestion->id}", [
            'decision' => 'invalid_decision',
        ]);

    $response->assertStatus(422);
});

it('batch updates suggestions', function () {
    $suggestion2 = CvSuggestion::factory()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$this->document->id}/suggestions/batch", [
            'decisions' => [
                ['id' => $this->suggestion->id, 'decision' => 'accepted'],
                ['id' => $suggestion2->id, 'decision' => 'rejected'],
            ],
        ]);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
});

it('rejects batch with invalid suggestion id', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$this->document->id}/suggestions/batch", [
            'decisions' => [
                ['id' => 99999, 'decision' => 'accepted'],
            ],
        ]);

    $response->assertStatus(422);
});

it('rejects review when document is not in ready_for_review state', function () {
    $document = CvDocument::factory()->pending()->create([
        'user_id' => $this->user->id,
    ]);
    $run = CvProcessingRun::factory()->create([
        'cv_document_id' => $document->id,
    ]);
    $suggestion = CvSuggestion::factory()->create([
        'cv_document_id' => $document->id,
        'cv_processing_run_id' => $run->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/cv/{$document->id}/suggestions/{$suggestion->id}", [
            'decision' => 'accepted',
        ]);

    $response->assertStatus(409);
});
