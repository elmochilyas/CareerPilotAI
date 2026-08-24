<?php

use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\JobOpportunity;
use App\Models\ProfileItem;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'dedup');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->document = CvDocument::factory()->readyForReview()->create(['user_id' => $this->user->id]);
    $this->run = CvProcessingRun::factory()->create(['cv_document_id' => $this->document->id]);
});

it('treats Backend Developer vs  backend developer  at same org as one item', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'field_name' => 'experience|backend developer|3lm solutions',
        'suggested_value' => [
            'title' => ' backend developer ',
            'organization' => ' 3LM Solutions ',
            'description' => 'Duplicate with whitespace/case',
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(1);
    expect($preview->json('data.conflicts.0.type'))->toBe('duplicate_item');

    $apply = $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ]);
    $apply->assertStatus(200);
    expect($apply->json('data.summary.items_created'))->toBe(0);
    expect($apply->json('data.summary.skipped'))->toBe(1);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
});

it('treats 3LM Solutions vs   3lm solutions  as one organization', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'suggested_value' => [
            'title' => 'Backend Developer',
            'organization' => '  3lm solutions  ',
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(1);

    $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ])->assertStatus(200);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
});

it('keeps Backend Developer vs Backend Developer Intern as separate when dates clearly separate', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'start_date' => '2020-01-01',
        'end_date' => '2021-12-31',
        'display_order' => 0,
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'suggested_value' => [
            'title' => 'Backend Developer Intern',
            'organization' => '3LM Solutions',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_current' => false,
        ],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    // Gap >180 days, same title/org similar but dates far apart => separate, not possible duplicate
    expect($preview->json('data.conflicts'))->toHaveCount(0);

    $apply = $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ]);
    $apply->assertStatus(200);
    expect($apply->json('data.summary.items_created'))->toBe(1);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(2);
});

it('treats French vs french as one language', function () {
    $this->profile->update(['languages' => [['language' => 'French', 'proficiency' => 'Native']]]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'language',
        'suggested_value' => ['language' => 'french', 'proficiency' => 'Advanced'],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(1);
    expect($preview->json('data.conflicts.0.type'))->toBe('duplicate_language');

    $apply = $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ]);
    $apply->assertStatus(200);
    expect($apply->json('data.summary.skipped'))->toBe(1);
    expect($apply->json('data.summary.fields_updated'))->toBe(0);

    $this->profile->refresh();
    expect($this->profile->languages)->toHaveCount(1);
    expect($this->profile->languages[0]['language'])->toBe('French');
});

it('treats canonical skill vs alias as one candidateSkill', function () {
    $skill = Skill::factory()->create(['name' => 'JavaScript', 'normalized_name' => 'javascript']);
    SkillAlias::factory()->create(['skill_id' => $skill->id, 'alias' => 'JS']);

    // Profile already has canonical skill
    CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $skill->id,
        'custom_skill_name' => null,
        'state' => 'claimed',
    ]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'skill',
        'suggested_value' => ['name' => 'JS', 'category' => 'Programming languages'],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    expect($preview->json('data.conflicts'))->toHaveCount(1);
    expect($preview->json('data.conflicts.0.type'))->toBe('duplicate_skill');

    $apply = $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ]);
    $apply->assertStatus(200);
    expect($apply->json('data.summary.skills_added'))->toBe(0);
    expect($apply->json('data.summary.skipped'))->toBe(1);

    expect(CandidateSkill::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
});

it('creates one CandidateSkill for two equivalent skill suggestions in same import without rollback', function () {
    $skill = Skill::factory()->create(['name' => 'PHP', 'normalized_name' => 'php']);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'skill',
        'suggested_value' => ['name' => 'PHP'],
    ]);
    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'skill',
        'suggested_value' => ['name' => ' php '],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    // Both resolve to same skill, but preview will see 2 conflicts after first maps? Actually first not yet applied, preview checks existingProfile (0), so 0 conflicts.
    // Apply should handle within-import dedup: first creates, second skipped.
    $apply = $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ]);
    $apply->assertStatus(200);
    expect($apply->json('data.status'))->toBe('applied');
    expect($apply->json('data.summary.skills_added'))->toBe(1);
    expect($apply->json('data.summary.skipped'))->toBe(1);
    expect($apply->json('data.summary.errors'))->toBe(0);

    expect(CandidateSkill::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
    $this->assertDatabaseHas('cv_documents', ['id' => $this->document->id, 'status' => 'imported']);
});

it('preview and apply agree on same incoming data', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);
    $skill = Skill::factory()->create(['name' => 'Laravel', 'normalized_name' => 'laravel']);
    CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $skill->id,
    ]);
    $this->profile->update(['languages' => [['language' => 'English', 'proficiency' => 'Advanced']]]);

    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'suggested_value' => ['title' => ' backend developer ', 'organization' => ' acme '],
    ]);
    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'skill',
        'suggested_value' => ['name' => 'laravel'],
    ]);
    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'language',
        'suggested_value' => ['language' => ' english ', 'proficiency' => 'Native'],
    ]);
    CvSuggestion::factory()->accepted()->create([
        'cv_document_id' => $this->document->id,
        'cv_processing_run_id' => $this->run->id,
        'type' => 'experience',
        'suggested_value' => ['title' => 'Frontend Developer', 'organization' => 'Acme'],
    ]);

    $preview = $this->actingAs($this->user)->getJson("/api/v1/cv/{$this->document->id}/import-preview");
    $preview->assertStatus(200);
    $previewConflicts = $preview->json('data.conflicts');
    // Should be 3 duplicates (experience backend, skill laravel, language english) and 1 new (frontend)
    expect($previewConflicts)->toHaveCount(3);

    $apply = $this->actingAs($this->user)->postJson("/api/v1/cv/{$this->document->id}/apply", [], [
        'Idempotency-Key' => hash('sha256', Str::uuid()->toString()),
    ]);
    $apply->assertStatus(200);
    // Apply should create 1 (frontend), skip 3
    expect($apply->json('data.summary.items_created'))->toBe(1);
    expect($apply->json('data.summary.skipped'))->toBe(3);
    expect($previewConflicts)->toHaveCount($apply->json('data.summary.skipped'));
});

it('tailored CV does not render exact-normalized duplicate records', function () {
    // Create duplicates directly in profile (old data) — bypassing new dedup
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme Corp',
        'display_order' => 0,
    ]);
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend developer ',
        'organization' => ' acme corp ',
        'display_order' => 1,
    ]);
    // Languages duplicates
    $this->profile->update(['languages' => [
        ['language' => 'French', 'proficiency' => 'Native'],
        ['language' => ' french ', 'proficiency' => 'Advanced'],
        ['language' => 'English', 'proficiency' => 'Advanced'],
    ]]);
    // Skills duplicates: canonical + custom same normalized
    $skill = Skill::factory()->create(['name' => 'PHP', 'normalized_name' => 'php']);
    CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $skill->id,
    ]);
    CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => null,
        'custom_skill_name' => ' php ',
    ]);

    $opportunity = JobOpportunity::factory()->create(['candidate_profile_id' => $this->profile->id]);
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $opportunity->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/tailor", ['use_ai' => false])
        ->assertOk();

    $resume->refresh();
    $content = $resume->content;

    // Experience should be deduped to 1
    expect($content['experience']['items'])->toHaveCount(1);
    // Languages deduped: French duplicates collapsed to 1 + English = 2
    expect($content['languages']['items'])->toHaveCount(2);
    $langTexts = array_column($content['languages']['items'], 'text');
    // Should have one French entry only
    $frenchCount = count(array_filter($langTexts, fn ($t) => str_contains(strtolower($t), 'french')));
    expect($frenchCount)->toBe(1);
    // Skills deduped to 1 (php)
    expect($content['skills']['items'])->toHaveCount(1);
    $skillTexts = array_column($content['skills']['items'], 'text');
    $phpCount = count(array_filter($skillTexts, fn ($t) => strtolower(trim($t)) === 'php'));
    expect($phpCount)->toBe(1);

    // Profile data itself still has duplicates (not deleted)
    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(2);
});
