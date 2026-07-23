<?php

use App\Domain\Profile\Services\ProfileCompletionService;
use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('scores an empty profile at zero', function () {
    $profile = CandidateProfile::factory()->empty()->create();
    expect(app(ProfileCompletionService::class)->calculate($profile)['score'])->toBe(0.0);
});

it('applies exact partial subweights', function () {
    $profile = CandidateProfile::factory()->empty()->create(['phone' => '+212600000000', 'city' => 'Casablanca', 'work_modes' => ['remote']]);
    expect(app(ProfileCompletionService::class)->calculate($profile)['score'])->toBe(10.0);
});

it('applies each scalar and array area weight independently', function (array $attributes, float $expected) {
    $profile = CandidateProfile::factory()->empty()->create($attributes);
    expect(app(ProfileCompletionService::class)->calculate($profile)['score'])->toBe($expected);
})->with([
    'headline' => [['headline' => 'Developer'], 10.0],
    'summary' => [['professional_summary' => 'A truthful summary.'], 15.0],
    'professional link' => [['github_url' => 'https://github.com/candidate'], 10.0],
    'target roles' => [['target_roles' => ['Developer']], 15.0],
    'work modes' => [['work_modes' => ['remote']], 3.0],
    'preferred locations' => [['preferred_locations' => ['Remote']], 4.0],
    'contract types' => [['contract_types' => ['full-time']], 3.0],
    'languages' => [['languages' => [['language' => 'English', 'proficiency' => 'fluent']]], 10.0],
]);

it('applies education and practical background weights independently', function (string $state, float $expected) {
    $profile = CandidateProfile::factory()->empty()->create();
    ProfileItem::factory()->{$state}()->for($profile)->create();
    expect(app(ProfileCompletionService::class)->calculate($profile->fresh())['score'])->toBe($expected);
})->with([['education', 10.0], ['experience', 10.0], ['project', 10.0]]);

it('scores all mandatory areas at 100 without certification or salary', function () {
    $profile = CandidateProfile::factory()->complete()->create(['salary_min' => null, 'salary_max' => null]);
    ProfileItem::factory()->education()->for($profile)->create();
    ProfileItem::factory()->experience()->for($profile)->create();
    expect(app(ProfileCompletionService::class)->calculate($profile->fresh())['score'])->toBe(100.0);
});

it('certifications never add points or appear as missing', function () {
    $profile = CandidateProfile::factory()->complete()->create();
    ProfileItem::factory()->education()->for($profile)->create();
    ProfileItem::factory()->experience()->for($profile)->create();
    $before = app(ProfileCompletionService::class)->calculate($profile->fresh());
    ProfileItem::factory()->certification()->for($profile)->create();
    $after = app(ProfileCompletionService::class)->calculate($profile->fresh());
    expect($after['score'])->toBe(100.0)->and($after['score'])->toBe($before['score'])->and(array_column($after['missing_areas'], 'key'))->not->toContain('certification');
});
