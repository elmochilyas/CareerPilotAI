<?php

use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'auth', 'bola');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

it('returns 404 for cross-user document view', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$document->id}");

    $response->assertStatus(404);
});

it('returns 404 for cross-user document delete', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/cv/{$document->id}");

    $response->assertStatus(404);
});

it('returns 404 for cross-user suggestions', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->otherUser->id,
    ]);
    $run = CvProcessingRun::factory()->create([
        'cv_document_id' => $document->id,
    ]);
    $suggestion = CvSuggestion::factory()->create([
        'cv_document_id' => $document->id,
        'cv_processing_run_id' => $run->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$document->id}/suggestions");

    $response->assertStatus(404);
});

it('returns 404 for cross-user suggestion review', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->otherUser->id,
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

    $response->assertStatus(404);
});

it('returns 404 for cross-user document download', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$document->id}/download");

    $response->assertStatus(404);
});

it('returns 404 for cross-user import preview', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$document->id}/import-preview");

    $response->assertStatus(404);
});

it('returns 404 for cross-user import apply', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$document->id}/apply");

    $response->assertStatus(404);
});

it('lists only own documents', function () {
    CvDocument::factory()->count(3)->create([
        'user_id' => $this->otherUser->id,
    ]);
    CvDocument::factory()->count(2)->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/cv');

    expect($response->json('data'))->toHaveCount(2);
});
