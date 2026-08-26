<?php

use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Matching\Services\StalenessService;
use App\Domain\Profile\Actions\DeduplicateLanguagesAction;
use App\Domain\Profile\Actions\MergeCandidateSkillsAction;
use App\Domain\Profile\Actions\MergeProfileItemsAction;
use App\Domain\Profile\Services\ProfileDuplicateDetector;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\ProfileItem;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class)->group('profile', 'cleanup');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
});

it('merges exact experience duplicates keeping one and merging metadata', function () {
    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'description' => 'Short',
        'location' => null,
        'start_date' => '2022-01-01',
        'end_date' => '2023-01-01',
        'display_order' => 0,
        'metadata' => ['employment_type' => 'full-time'],
    ]);

    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend developer ',
        'organization' => ' acme ',
        'description' => 'A much longer description that is richer and should be kept',
        'location' => 'Paris',
        'start_date' => '2022-01-01',
        'end_date' => null,
        'display_order' => 1,
        'metadata' => ['technologies' => ['PHP', 'Laravel']],
    ]);

    $action = app(MergeProfileItemsAction::class);
    $result = $action->execute($this->profile, $keep->id, [$dup->id]);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
    expect($result->id)->toBe($keep->id);
    expect($result->description)->toBe('A much longer description that is richer and should be kept');
    expect($result->location)->toBe('Paris');
    expect($result->end_date)->toBeNull(); // current wins
    expect($result->metadata['employment_type'])->toBe('full-time');
    expect($result->metadata['technologies'])->toContain('PHP');
});

it('does not merge rehire records with non-overlapping dates', function () {
    $first = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => '2020-01-01',
        'end_date' => '2021-12-31',
        'display_order' => 0,
    ]);

    $second = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => '2024-01-01',
        'end_date' => '2025-12-31',
        'display_order' => 1,
    ]);

    $detector = app(ProfileDuplicateDetector::class);
    $report = $detector->detectForProfile($this->profile);
    $exact = array_filter($report['groups'], fn ($g) => $g['classification'] === 'exact_duplicate');
    $legit = array_filter($report['groups'], fn ($g) => $g['classification'] === 'legitimate_separate');

    expect($exact)->toHaveCount(0);
    expect($legit)->toHaveCount(1);

    $action = app(MergeProfileItemsAction::class);
    expect(fn () => $action->execute($this->profile, $first->id, [$second->id]))->toThrow(ValidationException::class);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(2);
});

it('does not auto-merge fuzzy possible duplicates without explicit confirmation', function () {
    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => '3LM Solutions',
        'start_date' => '2024-01-01',
        'display_order' => 0,
    ]);

    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer Intern',
        'organization' => '3LM Solution',
        'start_date' => '2024-02-01',
        'display_order' => 1,
    ]);

    $detector = app(ProfileDuplicateDetector::class);
    $report = $detector->detectForProfile($this->profile);
    $possible = array_filter($report['groups'], fn ($g) => $g['classification'] === 'possible_duplicate');
    expect($possible)->toHaveCount(1);

    $action = app(MergeProfileItemsAction::class);
    expect(fn () => $action->execute($this->profile, $keep->id, [$dup->id]))->toThrow(ValidationException::class);

    // With explicit allowPossible, it should merge
    $result = $action->execute($this->profile, $keep->id, [$dup->id], true);
    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
});

it('merges canonical and custom equivalent skill keeping canonical and preserving evidence', function () {
    $skill = Skill::factory()->create(['name' => 'JavaScript', 'normalized_name' => 'javascript']);
    SkillAlias::factory()->create(['skill_id' => $skill->id, 'alias' => 'JS']);

    $canonical = CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $skill->id,
        'custom_skill_name' => null,
        'evidence' => [['type' => 'test', 'label' => 'canonical']],
        'proficiency_level' => 'intermediate',
        'state' => 'claimed',
    ]);

    $custom = CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => null,
        'custom_skill_name' => 'js',
        'evidence' => [['type' => 'test', 'label' => 'custom']],
        'proficiency_level' => 'advanced',
        'state' => 'verified',
    ]);

    $detector = app(ProfileDuplicateDetector::class);
    $report = $detector->detectForProfile($this->profile);
    $skillGroups = array_filter($report['groups'], fn ($g) => $g['entity_type'] === 'candidate_skills');
    expect($skillGroups)->toHaveCount(1);

    $action = app(MergeCandidateSkillsAction::class);
    $result = $action->execute($this->profile, $canonical->id, [$custom->id]);

    expect(CandidateSkill::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
    $kept = CandidateSkill::find($canonical->id);
    expect($kept->skill_id)->toBe($skill->id);
    expect($kept->custom_skill_name)->toBeNull();
    // Evidence merged
    expect($kept->evidence)->toHaveCount(2);
    // Strongest proficiency kept (advanced > intermediate)
    expect($kept->proficiency_level->value)->toBe('advanced');
    // Strongest state kept (verified > claimed)
    expect($kept->state->value)->toBe('verified');
});

it('preserves evidence during skill merge and prefers canonical', function () {
    $skill = Skill::factory()->create(['name' => 'PHP', 'normalized_name' => 'php']);

    $keep = CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $skill->id,
        'evidence' => [['type' => 'a', 'id' => 1]],
    ]);

    $dup = CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => null,
        'custom_skill_name' => 'PHP',
        'evidence' => [['type' => 'b', 'id' => 2]],
    ]);

    $action = app(MergeCandidateSkillsAction::class);
    $action->execute($this->profile, $keep->id, [$dup->id]);

    $kept = CandidateSkill::find($keep->id);
    expect($kept->evidence)->toHaveCount(2);
});

it('deduplicates French/french to one language keeping best proficiency', function () {
    $this->profile->update(['languages' => [
        ['language' => 'French', 'proficiency' => 'intermediate'],
        ['language' => 'french', 'proficiency' => 'native'],
        ['language' => '  French  ', 'proficiency' => 'basic'],
        ['language' => 'English', 'proficiency' => 'advanced'],
    ]]);

    $detector = app(ProfileDuplicateDetector::class);
    $report = $detector->detectForProfile($this->profile);
    $langGroups = array_filter($report['groups'], fn ($g) => $g['entity_type'] === 'languages');
    expect($langGroups)->toHaveCount(1);
    expect($langGroups[array_key_first($langGroups)]['record_ids'])->toHaveCount(3);

    $action = app(DeduplicateLanguagesAction::class);
    $result = $action->execute($this->profile);

    expect(count($result))->toBe(2);
    $french = array_values(array_filter($result, fn ($l) => strtolower(trim($l['language'])) === 'french'))[0];
    expect($french['proficiency'])->toBe('native'); // best

    $this->profile->refresh();
    expect(count($this->profile->languages))->toBe(2);
});

it('rolls back transaction on failure and leaves data intact', function () {
    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'A',
        'organization' => 'Org',
        'display_order' => 0,
    ]);

    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'B', // different title, not duplicate
        'organization' => 'Org2',
        'display_order' => 1,
    ]);

    $action = app(MergeProfileItemsAction::class);

    try {
        $action->execute($this->profile, $keep->id, [$dup->id]);
        $this->fail('Should have thrown ValidationException');
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('not compatible');
    }

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(2);
    expect(ProfileItem::find($keep->id))->not->toBeNull();
    expect(ProfileItem::find($dup->id))->not->toBeNull();
});

it('prevents cross-profile ownership bypass', function () {
    $otherUser = User::factory()->create();
    $otherProfile = CandidateProfile::factory()->create(['user_id' => $otherUser->id]);

    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);

    $otherItem = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $otherProfile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);

    $action = app(MergeProfileItemsAction::class);
    expect(fn () => $action->execute($this->profile, $keep->id, [$otherItem->id]))->toThrow(ValidationException::class);

    // Also via API
    $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/items', [
        'keep_id' => $keep->id,
        'duplicate_ids' => [$otherItem->id],
    ])->assertStatus(422);
});

it('touches profile and makes match stale', function () {
    $job = JobOpportunity::factory()->create(['candidate_profile_id' => $this->profile->id]);

    // Create an initial match analysis with current fingerprint
    $analysis = MatchAnalysis::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $job->id,
        'profile_fingerprint' => app(FingerprintService::class)->profile(
            ProfileSnapshot::fromCandidateProfile($this->profile)
        ),
        'profile_updated_at' => $this->profile->updated_at,
    ]);

    $originalUpdatedAt = $this->profile->updated_at->copy();

    sleep(1);

    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);

    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend ',
        'organization' => ' acme ',
        'display_order' => 1,
    ]);

    $action = app(MergeProfileItemsAction::class);
    $action->execute($this->profile, $keep->id, [$dup->id]);

    $this->profile->refresh();
    expect($this->profile->updated_at->greaterThan($originalUpdatedAt))->toBeTrue();

    $staleService = app(StalenessService::class);
    expect($staleService->isStale($analysis->fresh(), $this->profile->fresh(), $job))->toBeTrue();
});

it('does not modify approved resume', function () {
    $job = JobOpportunity::factory()->create(['candidate_profile_id' => $this->profile->id]);

    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);

    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend ',
        'organization' => ' acme ',
        'display_order' => 1,
    ]);

    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $job->id,
        'content' => ['experience' => ['items' => [['source_id' => $keep->id, 'text' => 'old']]]],
    ]);

    $originalContent = $resume->content;
    $originalApprovedAt = $resume->approved_at;

    $action = app(MergeProfileItemsAction::class);
    $action->execute($this->profile, $keep->id, [$dup->id]);

    $resume->refresh();
    expect($resume->content)->toEqual($originalContent);
    expect($resume->approved_at->equalTo($originalApprovedAt))->toBeTrue();
    expect($resume->status->value)->toBe('approved');
});

it('is idempotent when run twice', function () {
    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);

    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend ',
        'organization' => ' acme ',
        'display_order' => 1,
    ]);

    $action = app(MergeProfileItemsAction::class);
    $action->execute($this->profile, $keep->id, [$dup->id]);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);

    // Second run with same ids should fail gracefully (dup not found) or be no-op
    // We test that running detector again reports no exact duplicates
    $detector = app(ProfileDuplicateDetector::class);
    $report = $detector->detectForProfile($this->profile->fresh());
    $exact = array_filter($report['groups'], fn ($g) => $g['classification'] === 'exact_duplicate');
    expect($exact)->toHaveCount(0);

    // Also test that trying to merge already deleted dup throws validation but does not delete keep
    expect(fn () => $action->execute($this->profile->fresh(), $keep->id, [$dup->id]))->toThrow(ValidationException::class);
    expect(ProfileItem::find($keep->id))->not->toBeNull();
});

it('exposes duplicates via API and respects ownership', function () {
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);
    ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend ',
        'organization' => ' acme ',
        'display_order' => 1,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/profile/duplicates');
    $response->assertStatus(200);
    expect($response->json('data.groups'))->toHaveCount(1);
    expect($response->json('data.groups.0.classification'))->toBe('exact_duplicate');

    $otherUser = User::factory()->create();
    $response2 = $this->actingAs($otherUser)->getJson('/api/v1/profile/duplicates');
    $response2->assertStatus(200);
    expect($response2->json('data.groups'))->toHaveCount(0);
});

it('rejects malformed merge payload via API validation', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/items', []);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['keep_id', 'duplicate_ids']);
    expect($response->json('code'))->toBe('validation_error');
    expect($response->json('request_id'))->toBeString();

    $response2 = $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/items', [
        'keep_id' => 999999,
        'duplicate_ids' => [],
    ]);
    $response2->assertStatus(422);
    expect($response2->json('code'))->toBe('validation_error');
});

it('rejects duplicate values inside duplicate_ids', function () {
    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);
    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend ',
        'organization' => ' acme ',
        'display_order' => 1,
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/items', [
        'keep_id' => $keep->id,
        'duplicate_ids' => [$dup->id, $dup->id],
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['duplicate_ids.1']);
    expect($response->json('code'))->toBe('validation_error');

    $skill = Skill::factory()->create(['name' => 'JS', 'normalized_name' => 'js']);
    $keepSkill = CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $skill->id,
    ]);
    $dupSkill = CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => null,
        'custom_skill_name' => 'JS',
    ]);

    $response2 = $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/skills', [
        'keep_id' => $keepSkill->id,
        'duplicate_ids' => [$dupSkill->id, $dupSkill->id],
    ]);
    $response2->assertStatus(422);
});

it('rejects keep_id included in duplicate_ids at request validation', function () {
    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/items', [
        'keep_id' => $keep->id,
        'duplicate_ids' => [$keep->id],
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['keep_id']);
    expect($response->json('code'))->toBe('validation_error');
    expect($response->json('request_id'))->toBeString();
});

it('rejects non-boolean allow_possible', function () {
    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);
    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend ',
        'organization' => ' acme ',
        'display_order' => 1,
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/items', [
        'keep_id' => $keep->id,
        'duplicate_ids' => [$dup->id],
        'allow_possible' => 'not-a-boolean',
    ]);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['allow_possible']);
});

it('rejects client-supplied profile ownership field', function () {
    $keep = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend',
        'organization' => 'Acme',
        'display_order' => 0,
    ]);
    $dup = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => ' backend ',
        'organization' => ' acme ',
        'display_order' => 1,
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/items', [
        'keep_id' => $keep->id,
        'duplicate_ids' => [$dup->id],
        'candidate_profile_id' => 9999,
    ]);
    $response->assertStatus(422);
    expect($response->json('code'))->toBe('validation_error');
});

it('returns rfc9457 shape with request_id on validation failures', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/profile/cleanup/skills', [
        'keep_id' => 'not-an-int',
        'duplicate_ids' => 'not-an-array',
    ]);
    $response->assertStatus(422);
    $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'code', 'errors', 'request_id']);
    expect($response->json('code'))->toBe('validation_error');
    expect($response->json('status'))->toBe(422);
    expect($response->json('request_id'))->toBeString();
});
