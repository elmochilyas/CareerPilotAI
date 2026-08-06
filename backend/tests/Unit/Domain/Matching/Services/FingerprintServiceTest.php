<?php

use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\OpportunitySnapshot;

beforeEach(function () {
    $this->service = new FingerprintService;
});

it('produces identical fingerprints for identical profile snapshots', function () {
    $snapshot = matchProfileSnapshot(
        skills: [['id' => 1, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []]],
    );

    expect($this->service->profile($snapshot))->toBe($this->service->profile(matchProfileSnapshot(
        skills: [['id' => 1, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []]],
    )));
});

it('changes the profile fingerprint when a verified skill is added', function () {
    $base = matchProfileSnapshot(
        skills: [['id' => 1, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []]],
    );
    $changed = matchProfileSnapshot(
        skills: [
            ['id' => 1, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []],
            ['id' => 2, 'normalized_name' => 'docker', 'state' => 'verified', 'years_experience' => 2.0, 'evidence' => []],
        ],
    );

    expect($this->service->profile($base))->not->toBe($this->service->profile($changed));
});

it('is order-independent for the same underlying profile data', function () {
    $first = matchProfileSnapshot(
        skills: [
            ['id' => 1, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []],
            ['id' => 2, 'normalized_name' => 'laravel', 'state' => 'claimed', 'years_experience' => 3.0, 'evidence' => []],
        ],
    );
    $reversed = matchProfileSnapshot(
        skills: [
            ['id' => 2, 'normalized_name' => 'laravel', 'state' => 'claimed', 'years_experience' => 3.0, 'evidence' => []],
            ['id' => 1, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []],
        ],
    );

    expect($this->service->profile($first))->toBe($this->service->profile($reversed));
});

it('produces a canonical array with sorted keys', function () {
    $canonical = matchProfileSnapshot(
        skills: [['id' => 1, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []]],
    )->toCanonicalArray();

    expect(array_keys($canonical))->toBe([
        'contract_types',
        'headline',
        'items',
        'languages',
        'professional_summary',
        'skills',
        'target_roles',
        'work_modes',
    ]);
});

it('produces identical fingerprints for identical opportunity snapshots', function () {
    expect($this->service->opportunity(matchOpportunitySnapshot()))
        ->toBe($this->service->opportunity(matchOpportunitySnapshot()));
});

it('changes the opportunity fingerprint when a requirement changes', function () {
    $base = matchOpportunitySnapshot();
    $changed = new OpportunitySnapshot(
        title: 'Senior PHP Developer',
        companyName: 'Acme',
        summary: 'Build APIs',
        workMode: 'remote',
        contractType: 'full-time',
        seniorityLevel: 'senior',
        requirements: [
            ['id' => 1, 'category' => 'education', 'content' => 'PhD', 'classification' => 'required', 'language' => null, 'display_order' => 0],
        ],
        skills: [
            ['id' => 2, 'normalized_name' => 'php', 'original_label' => 'PHP', 'classification' => 'required', 'display_order' => 0],
        ],
    );

    expect($this->service->opportunity($base))->not->toBe($this->service->opportunity($changed));
});
