<?php

use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\ProfileItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'import');

function makeReadyForImportDocument(User $user, array $metadata): CvDocument
{
    $document = CvDocument::factory()->readyForReview()->create([
        'user_id' => $user->id,
        'metadata' => $metadata,
    ]);

    CvProcessingRun::factory()->create([
        'cv_document_id' => $document->id,
    ]);

    return $document;
}

function addAcceptedCvSuggestion(CvDocument $document, string $type, array $value, array $extra = []): CvSuggestion
{
    return CvSuggestion::factory()->accepted()->create(array_merge([
        'cv_document_id' => $document->id,
        'cv_processing_run_id' => $document->processingRuns()->first()->id,
        'type' => $type,
        'suggested_value' => $value,
    ], $extra));
}

function freshIdempotencyKey(): string
{
    return hash('sha256', Str::uuid()->toString());
}

it('creates a candidate profile when mode is create_new and none exists', function () {
    $user = User::factory()->create();
    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Laravel Developer']);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('applied');

    $profile = CandidateProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull();
    expect($profile->headline)->toBe('Senior Laravel Developer');
    expect(CandidateProfile::where('user_id', $user->id)->count())->toBe(1);

    $this->assertDatabaseHas('cv_documents', ['id' => $document->id, 'status' => 'imported']);
    expect($document->fresh()->metadata['mode'])->toBe('create_new');
});

it('updates the existing profile when mode is create_new and a profile already exists', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Updated Headline']);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(200);
    expect(CandidateProfile::where('user_id', $user->id)->count())->toBe(1);
    expect($profile->fresh()->headline)->toBe('Updated Headline');
});

it('defaults to create_new when the document carries no mode', function () {
    $user = User::factory()->create();
    $document = makeReadyForImportDocument($user, ['source' => 'test']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Fallback Headline']);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(200);
    expect(CandidateProfile::where('user_id', $user->id)->count())->toBe(1);
    expect(CandidateProfile::where('user_id', $user->id)->first()->headline)->toBe('Fallback Headline');
});

it('rejects apply when mode is update_existing and no profile exists', function () {
    $user = User::factory()->create();
    $document = makeReadyForImportDocument($user, ['mode' => 'update_existing']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Laravel Developer']);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('profile_required_for_update');

    expect(CandidateProfile::where('user_id', $user->id)->count())->toBe(0);
    $this->assertDatabaseHas('cv_documents', ['id' => $document->id, 'status' => 'ready_for_review']);

    $batch = CvImportBatch::where('cv_document_id', $document->id)->first();
    expect($batch->status->value)->toBe('failed');
    expect($batch->failure_code)->toBe('import_failed');

    $suggestion = $document->suggestions()->first();
    expect($suggestion->import_status)->toBeNull();
});

it('applies when mode is update_existing and a profile exists', function () {
    $user = User::factory()->create();
    CandidateProfile::factory()->create(['user_id' => $user->id]);
    $document = makeReadyForImportDocument($user, ['mode' => 'update_existing']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Merged Headline']);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(200);
    expect(CandidateProfile::where('user_id', $user->id)->count())->toBe(1);
    expect(CandidateProfile::where('user_id', $user->id)->first()->headline)->toBe('Merged Headline');
});

it('treats blank or whitespace profile_updated_at as absent', function () {
    foreach (['', '   ', "\t\n"] as $blank) {
        $user = User::factory()->create();
        CandidateProfile::factory()->create(['user_id' => $user->id]);
        $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);
        addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Developer']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/cv/{$document->id}/apply", [
                'profile_updated_at' => $blank,
            ], [
                'Idempotency-Key' => freshIdempotencyKey(),
            ]);

        $response->assertStatus(200);
        expect($response->json('data.status'))->toBe('applied');
    }
});

it('rejects a stale profile_updated_at with profile_changed', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Developer']);

    $originalUpdatedAt = $profile->fresh()->updated_at->toISOString();
    $profile->touch();

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [
            'profile_updated_at' => $originalUpdatedAt,
        ], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('profile_changed');
});

it('marks every accepted suggestion as included or skipped, never silently dropped', function () {
    $user = User::factory()->create();
    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);

    addAcceptedCvSuggestion($document, 'basic_information', ['value' => '0601020304'], ['field_name' => 'phone']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Laravel Developer']);
    addAcceptedCvSuggestion($document, 'summary', ['value' => 'Backend engineer with five years of experience.']);
    addAcceptedCvSuggestion($document, 'skill', ['name' => 'PHP', 'category' => 'language']);
    addAcceptedCvSuggestion($document, 'language', ['language' => 'French', 'proficiency' => 'Advanced']);
    addAcceptedCvSuggestion($document, 'social_link', ['url' => 'https://linkedin.com/in/ilyas'], ['category' => 'linkedin']);
    addAcceptedCvSuggestion($document, 'experience', [
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'description' => 'Built REST APIs.',
        'start_date' => '2023-01',
    ]);
    addAcceptedCvSuggestion($document, 'education', [
        'degree' => 'Master',
        'institution' => 'ENSAM',
        'start_date' => '2019-09',
    ]);
    addAcceptedCvSuggestion($document, 'project', [
        'name' => 'CareerPilot',
        'description' => 'AI career platform.',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(200);

    $suggestions = CvSuggestion::where('cv_document_id', $document->id)->get();
    expect($suggestions)->toHaveCount(9);
    foreach ($suggestions as $suggestion) {
        expect($suggestion->import_status)->toBeIn(['included', 'skipped']);
    }

    $batch = CvImportBatch::where('cv_document_id', $document->id)->first();
    expect($batch->status->value)->toBe('applied');
    expect($batch->summary['total_accepted'])->toBe(9);
    expect($batch->summary['errors'])->toBe(0);

    $profile = CandidateProfile::where('user_id', $user->id)->first();
    expect($profile->phone)->toBe('0601020304');
    expect($profile->headline)->toBe('Senior Laravel Developer');
    expect($profile->professional_summary)->toContain('five years');
    expect($profile->linkedin_url)->toBe('https://linkedin.com/in/ilyas');
    expect($profile->languages)->toContain(['language' => 'French', 'proficiency' => 'Advanced']);

    expect(CandidateSkill::where('candidate_profile_id', $profile->id)->first()->custom_skill_name)->toBe('PHP');
});

it('normalizes French month names and bare years in imported profile item dates', function () {
    $user = User::factory()->create();
    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);

    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Developer']);
    addAcceptedCvSuggestion($document, 'experience', [
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => 'Juillet 2026',
        'end_date' => 'mars 2026',
    ]);
    addAcceptedCvSuggestion($document, 'education', [
        'degree' => 'Master',
        'institution' => 'ENSAM',
        'start_date' => '2022',
        'end_date' => 'présent',
    ]);
    addAcceptedCvSuggestion($document, 'project', [
        'name' => 'CareerPilot',
        'description' => 'AI career platform.',
        'start_date' => 'janv. 2024',
        'end_date' => 'incomprehensible-date',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('applied');

    $profile = CandidateProfile::where('user_id', $user->id)->firstOrFail();
    $items = ProfileItem::where('candidate_profile_id', $profile->id)->get()->keyBy('type');

    expect($items['experience']->start_date?->toDateString())->toBe('2026-07-01');
    expect($items['experience']->end_date?->toDateString())->toBe('2026-03-01');

    expect($items['education']->start_date?->toDateString())->toBe('2022-01-01');
    expect($items['education']->end_date)->toBeNull();

    expect($items['project']->start_date?->toDateString())->toBe('2024-01-01');
    expect($items['project']->end_date)->toBeNull();
});

it('normalizes dates and honors is_current when updating an existing profile item', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
    $existingItem = ProfileItem::factory()->create([
        'candidate_profile_id' => $profile->id,
        'type' => 'experience',
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => '2022-01-01',
        'end_date' => null,
    ]);

    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Developer']);
    addAcceptedCvSuggestion($document, 'experience', [
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => 'février 2023',
        'end_date' => 'juil. 2023',
        'is_current' => true,
    ], ['review_status' => 'update_existing']);

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(200);

    $item = $existingItem->fresh();
    expect($item->start_date?->toDateString())->toBe('2023-02-01');
    expect($item->end_date)->toBeNull();
});

it('rolls back every write when apply fails mid-transaction', function () {
    $user = User::factory()->create();
    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Developer']);
    addAcceptedCvSuggestion($document, 'skill', ['name' => 'PHP', 'category' => 'language']);

    DB::statement(
        'CREATE TRIGGER force_import_failure
         AFTER INSERT ON candidate_skills
         BEGIN
            SELECT RAISE(ABORT, \'forced apply failure\');
         END'
    );

    $response = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);

    $response->assertStatus(500);

    expect(CandidateProfile::count())->toBe(0);
    expect(ProfileItem::count())->toBe(0);
    expect(CandidateSkill::count())->toBe(0);

    $this->assertDatabaseHas('cv_documents', ['id' => $document->id, 'status' => 'ready_for_review']);

    $batch = CvImportBatch::where('cv_document_id', $document->id)->first();
    expect($batch->status->value)->toBe('failed');
    expect($batch->failure_code)->toBe('import_failed');

    foreach ($document->suggestions()->get() as $suggestion) {
        expect($suggestion->import_status)->toBeNull();
    }
});

it('rejects a retry with the same idempotency key', function () {
    $user = User::factory()->create();
    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Developer']);

    $idempotencyKey = freshIdempotencyKey();

    $first = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    $first->assertStatus(200);

    $second = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    $second->assertStatus(409);
    expect($second->json('code'))->toBe('import_already_applied');
});

it('rejects a second apply with a new key once the document is imported', function () {
    $user = User::factory()->create();
    $document = makeReadyForImportDocument($user, ['mode' => 'create_new']);
    addAcceptedCvSuggestion($document, 'headline', ['value' => 'Senior Developer']);

    $first = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);
    $first->assertStatus(200);

    $second = $this->actingAs($user)
        ->postJson("/api/v1/cv/{$document->id}/apply", [], [
            'Idempotency-Key' => freshIdempotencyKey(),
        ]);
    $second->assertStatus(409);
    expect($second->json('code'))->toBe('document_not_ready_for_import');
});
