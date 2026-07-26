<?php

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Models\CvDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'lifecycle');

beforeEach(function () {
    Storage::fake('cv-ingestion');
    $this->user = User::factory()->create();
});

it('shows a single document', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$document->id}");

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($document->id);
});

it('deletes a document', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/cv/{$document->id}");

    $response->assertStatus(200);

    $this->assertDatabaseHas('cv_documents', [
        'id' => $document->id,
        'status' => CvDocumentStatus::Deleted->value,
    ]);
});

it('retries a failed document', function () {
    Queue::fake();

    $document = CvDocument::factory()->failed()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$document->id}/retry");

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('queued');
});

it('rejects retry for non-failed document', function () {
    $document = CvDocument::factory()->readyForReview()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/cv/{$document->id}/retry");

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('document_not_retryable');
});

it('returns 404 for deleted document', function () {
    $document = CvDocument::factory()->deleted()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$document->id}");

    $response->assertStatus(200);
});

it('downloads a document', function () {
    Storage::fake('local');
    Storage::disk('local')->makeDirectory('cv-ingestion');

    $content = '%PDF-1.4 test document content';
    Storage::disk('local')->put('cv-ingestion/test-cv.pdf', $content);

    $document = CvDocument::factory()->create([
        'user_id' => $this->user->id,
        'stored_path' => 'cv-ingestion/test-cv.pdf',
        'stored_name' => 'test-cv.pdf',
        'mime_type' => 'application/pdf',
        'original_name' => 'cv.pdf',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$document->id}/download");

    $response->assertStatus(200);
});
