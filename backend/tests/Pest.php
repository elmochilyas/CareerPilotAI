<?php

use App\Domain\Matching\Services\OpportunitySnapshot;
use App\Domain\Matching\Services\ProfileSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

if (! function_exists('matchProfileSnapshot')) {
    function matchProfileSnapshot(array $skills = [], array $items = [], array $languages = []): ProfileSnapshot
    {
        return new ProfileSnapshot(
            headline: 'Backend Developer',
            professionalSummary: null,
            targetRoles: ['Backend Developer'],
            workModes: ['remote'],
            contractTypes: ['full-time'],
            languages: $languages,
            skills: $skills,
            items: $items,
        );
    }
}

if (! function_exists('matchOpportunitySnapshot')) {
    function matchOpportunitySnapshot(): OpportunitySnapshot
    {
        return new OpportunitySnapshot(
            title: 'Senior PHP Developer',
            companyName: 'Acme',
            summary: 'Build APIs',
            workMode: 'remote',
            contractType: 'full-time',
            seniorityLevel: 'senior',
            requirements: [
                ['id' => 1, 'category' => 'education', 'content' => "Master's degree", 'classification' => 'required', 'language' => null, 'display_order' => 0],
            ],
            skills: [
                ['id' => 2, 'normalized_name' => 'php', 'original_label' => 'PHP', 'classification' => 'required', 'display_order' => 0],
            ],
        );
    }
}

function something()
{
    // ..
}
