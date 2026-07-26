<?php

use App\Models\CvDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'upload');

beforeEach(function () {
    Queue::fake();
    Storage::fake('cv-ingestion');
    $this->user = User::factory()->create();
});

it('rejects unauthenticated upload', function () {
    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    $response = $this->postJson('/api/v1/cv', ['file' => $file]);

    $response->assertStatus(401);
});

it('uploads a valid PDF', function () {
    $file = UploadedFile::fake()->createWithContent(
        'cv.pdf',
        '%PDF-1.4'.str_repeat("\n1 0 obj\n<< /Type /Catalog >>\nendobj", 20),
        'application/pdf',
    );

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/cv', ['file' => $file]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['data' => ['id', 'status', 'original_name']]);
    expect($response->json('data.status'))->toBe('queued');
    expect($response->json('data.original_name'))->toBe('cv.pdf');

    $this->assertDatabaseHas('cv_documents', [
        'user_id' => $this->user->id,
        'status' => 'queued',
    ]);
});

it('uploads a valid DOCX', function () {
    $file = UploadedFile::fake()->createWithContent(
        'cv.docx',
        'PK'.str_repeat("\x03\x04\x00\x00\x00\x00", 300),
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    );

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/cv', ['file' => $file]);

    $response->assertStatus(201);
    expect($response->json('data.status'))->toBe('queued');
});

it('rejects invalid file type', function () {
    $file = UploadedFile::fake()->create('cv.txt', 100, 'text/plain');

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/cv', ['file' => $file]);

    $response->assertStatus(422);
});

it('rejects file that is too large', function () {
    $maxSize = config('cv-ingestion.max_file_size', 20971520);
    $file = UploadedFile::fake()->create('cv.pdf', $maxSize + 1, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/cv', ['file' => $file]);

    $response->assertStatus(422);
});

it('returns file_duplicate for identical upload', function () {
    $content = '%PDF-1.4 test content '.str_repeat('x', 300);
    $file1 = UploadedFile::fake()->createWithContent('cv.pdf', $content, 'application/pdf');

    $this->actingAs($this->user)->postJson('/api/v1/cv', ['file' => $file1]);

    $file2 = UploadedFile::fake()->createWithContent('cv2.pdf', $content, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/cv', ['file' => $file2]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('file_duplicate');
    expect($response->json('errors.existing_cv_id'))->toBeInt();
});

it('restores soft-deleted document on re-upload of same content', function () {
    $content = '%PDF-1.4 test content '.str_repeat('w', 300);
    $file = UploadedFile::fake()->createWithContent('cv.pdf', $content, 'application/pdf');
    $checksum = hash_file('sha256', $file->getRealPath());

    $document = CvDocument::factory()->create([
        'user_id' => $this->user->id,
        'checksum' => $checksum,
        'status' => 'ready_for_review',
    ]);

    $this->actingAs($this->user)->deleteJson("/api/v1/cv/{$document->id}");
    $this->assertDatabaseHas('cv_documents', ['id' => $document->id, 'status' => 'deleted']);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/cv', ['file' => $file]);

    $response->assertStatus(201);
    expect($response->json('data.id'))->toBe($document->id);
    expect($response->json('data.status'))->toBe('queued');
});

it('lists uploaded documents', function () {
    $file = UploadedFile::fake()->createWithContent(
        'cv.pdf',
        '%PDF-1.4'.str_repeat("\n1 0 obj\n<< /Type /Catalog >>\nendobj", 20),
        'application/pdf',
    );
    $this->actingAs($this->user)->postJson('/api/v1/cv', ['file' => $file]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/cv');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('returns 404 for cross-user document', function () {
    $otherUser = User::factory()->create();
    $document = CvDocument::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/cv/{$document->id}");

    $response->assertStatus(404);
});
