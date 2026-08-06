<?php

use App\Domain\Matching\Data\MatchRequirement;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Matching\Services\RequirementCollector;
use App\Models\JobOpportunity;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('matching', 'requirement-collector');

function matchJobOpportunity(): JobOpportunity
{
    return JobOpportunity::factory()->create();
}

beforeEach(function () {
    $this->collector = new RequirementCollector;
});

it('maps responsibilities and certifications to the evidence category', function () {
    $opportunity = matchJobOpportunity();

    $opportunity->requirements()->create([
        'category' => 'responsibility',
        'content' => 'Design scalable systems',
        'classification' => 'required',
        'display_order' => 0,
    ]);
    $opportunity->requirements()->create([
        'category' => 'required_certification',
        'content' => 'AWS Certified Developer',
        'classification' => 'required',
        'display_order' => 1,
    ]);
    $opportunity->requirements()->create([
        'category' => 'preferred_certification',
        'content' => 'CKA',
        'classification' => 'preferred',
        'display_order' => 2,
    ]);

    $requirements = $this->collector->collect($opportunity);

    expect($requirements)->toHaveCount(3);
    expect($requirements[0]->category)->toBe(MatchCategory::Evidence->value);
    expect($requirements[0]->importance)->toBe(MatchImportance::Required);
    expect($requirements[1]->category)->toBe(MatchCategory::Evidence->value);
    expect($requirements[2]->category)->toBe(MatchCategory::Evidence->value);
    expect($requirements[2]->importance)->toBe(MatchImportance::Preferred);
});

it('maps experience and education requirements to the experience_education category', function () {
    $opportunity = matchJobOpportunity();

    $opportunity->requirements()->create([
        'category' => 'required_experience',
        'content' => 'Backend development (3 years)',
        'classification' => 'required',
        'display_order' => 0,
    ]);
    $opportunity->requirements()->create([
        'category' => 'preferred_experience',
        'content' => 'Team leadership',
        'classification' => 'preferred',
        'display_order' => 1,
    ]);
    $opportunity->requirements()->create([
        'category' => 'education',
        'content' => "Master's degree",
        'classification' => 'required',
        'display_order' => 2,
    ]);

    $requirements = $this->collector->collect($opportunity);

    expect($requirements)->toHaveCount(3);
    expect($requirements[0]->category)->toBe(MatchCategory::ExperienceEducation->value);
    expect($requirements[1]->importance)->toBe(MatchImportance::Preferred);
    expect($requirements[2]->category)->toBe(MatchCategory::ExperienceEducation->value);
});

it('maps language requirements to the language_soft category and keeps the language', function () {
    $opportunity = matchJobOpportunity();

    $opportunity->requirements()->create([
        'category' => 'language',
        'content' => 'English',
        'classification' => 'required',
        'language' => 'English',
        'display_order' => 0,
    ]);

    $requirements = $this->collector->collect($opportunity);
    $requirement = $requirements[0];

    expect($requirement->category)->toBe(MatchCategory::LanguageSoft->value);
    expect($requirement->language)->toBe('English');
    expect($requirement->sourceType)->toBe(RequirementSourceType::JobRequirement);
});

it('drops requirements with unrecognized categories', function () {
    $opportunity = matchJobOpportunity();

    $opportunity->requirements()->create([
        'category' => 'company_culture',
        'content' => 'Startup mindset',
        'classification' => 'required',
        'display_order' => 0,
    ]);

    expect($this->collector->collect($opportunity))->toBeEmpty();
});

it('maps opportunity skills to required and preferred skill categories', function () {
    $opportunity = matchJobOpportunity();
    $skill = Skill::factory()->create(['name' => 'Laravel', 'normalized_name' => 'laravel']);

    $opportunity->skills()->create([
        'skill_id' => $skill->id,
        'original_label' => 'Laravel',
        'classification' => 'required',
        'display_order' => 0,
    ]);
    $opportunity->skills()->create([
        'skill_id' => $skill->id,
        'original_label' => 'Vue',
        'classification' => 'preferred',
        'display_order' => 1,
    ]);

    $requirements = $this->collector->collect($opportunity);

    expect($requirements)->toHaveCount(2);
    expect($requirements[0]->category)->toBe(MatchCategory::RequiredSkills->value);
    expect($requirements[0]->importance)->toBe(MatchImportance::Required);
    expect($requirements[0]->text)->toBe('Laravel');
    expect($requirements[1]->category)->toBe(MatchCategory::PreferredSkills->value);
    expect($requirements[1]->importance)->toBe(MatchImportance::Preferred);
    expect($requirements[1]->sourceType)->toBe(RequirementSourceType::JobOpportunitySkill);
});

it('keeps display order across requirement sources', function () {
    $opportunity = matchJobOpportunity();
    $skill = Skill::factory()->create();

    $opportunity->requirements()->create([
        'category' => 'responsibility',
        'content' => 'First',
        'classification' => 'required',
        'display_order' => 0,
    ]);
    $opportunity->skills()->create([
        'skill_id' => $skill->id,
        'original_label' => 'Second',
        'classification' => 'required',
        'display_order' => 0,
    ]);

    $requirements = $this->collector->collect($opportunity);

    expect($requirements[0]->displayOrder)->toBe(0);
    expect($requirements[1]->displayOrder)->toBe(1);
});

it('caps the number of collected requirements', function () {
    config(['matching.max_requirements_per_analysis' => 2]);

    $opportunity = matchJobOpportunity();

    foreach (['A', 'B', 'C'] as $index => $content) {
        $opportunity->requirements()->create([
            'category' => 'responsibility',
            'content' => $content,
            'classification' => 'required',
            'display_order' => $index,
        ]);
    }

    $requirements = $this->collector->collect($opportunity);

    expect($requirements)->toHaveCount(2);
    expect(array_map(
        static fn (MatchRequirement $requirement): string => $requirement->text,
        $requirements,
    ))->toBe(['A', 'B']);
});

it('truncates requirement text to the configured maximum length', function () {
    $opportunity = matchJobOpportunity();

    $opportunity->requirements()->create([
        'category' => 'responsibility',
        'content' => str_repeat('x', 600),
        'classification' => 'required',
        'display_order' => 0,
    ]);

    $requirements = $this->collector->collect($opportunity);

    expect($requirements)->toHaveCount(1);
    expect(strlen($requirements[0]->text))->toBe(500);
    expect($requirements[0]->text)->toStartWith(str_repeat('x', 500));
});

it('keeps requirement text under the maximum length unchanged', function () {
    $opportunity = matchJobOpportunity();

    $content = str_repeat('x', 500);

    $opportunity->requirements()->create([
        'category' => 'responsibility',
        'content' => $content,
        'classification' => 'required',
        'display_order' => 0,
    ]);

    $requirements = $this->collector->collect($opportunity);

    expect($requirements)->toHaveCount(1);
    expect($requirements[0]->text)->toBe($content);
});

it('truncates opportunity skill labels to the configured maximum length', function () {
    $opportunity = matchJobOpportunity();
    $skill = Skill::factory()->create(['name' => 'Laravel', 'normalized_name' => 'laravel']);

    $opportunity->skills()->create([
        'skill_id' => $skill->id,
        'original_label' => str_repeat('x', 600),
        'classification' => 'required',
        'display_order' => 0,
    ]);

    $requirements = $this->collector->collect($opportunity);

    expect($requirements)->toHaveCount(1);
    expect(strlen($requirements[0]->text))->toBe(500);
});
