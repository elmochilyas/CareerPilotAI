<?php

use App\Domain\Opportunities\Data\JobAnalysisResult;
use App\Domain\Opportunities\Services\JobAnalysisSchemaValidator;
use App\Exceptions\Api\ConflictException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->validator = new JobAnalysisSchemaValidator;
});

it('validates a complete structured response', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Senior Laravel Developer',
            'company' => 'TechCorp',
            'department' => 'Engineering',
            'summary' => 'We are looking for a Laravel developer.',
            'application_url' => 'https://example.com/apply',
            'location' => [
                'city' => 'Casablanca',
                'region' => 'Casablanca-Settat',
                'country' => 'Morocco',
            ],
            'work_mode' => 'hybrid',
            'contract_type' => 'full-time',
            'seniority_level' => 'senior',
            'working_hours' => '40h/week',
            'travel_required' => false,
            'relocation_required' => false,
            'responsibilities' => [
                ['text' => 'Develop and maintain Laravel applications'],
                ['text' => 'Lead code reviews'],
            ],
            'required_experience' => [
                ['summary' => 'Experience with Laravel', 'years' => 5],
            ],
            'preferred_experience' => [
                ['summary' => 'Experience with Vue.js', 'years' => 2],
            ],
            'education_requirements' => [
                ['degree' => 'Bachelor', 'field' => 'CS'],
            ],
            'required_skills' => [
                ['label' => 'Laravel', 'classification' => 'required', 'level' => 'advanced'],
                ['label' => 'PHP', 'classification' => 'required', 'level' => 'advanced'],
            ],
            'preferred_skills' => [
                ['label' => 'Vue.js', 'classification' => 'preferred', 'level' => 'intermediate'],
            ],
            'languages' => [
                ['language' => 'English', 'proficiency' => 'advanced'],
            ],
            'certifications' => [
                ['name' => 'AWS Certified Developer'],
            ],
            'compensation' => [
                'salary_min' => 60000,
                'salary_max' => 90000,
                'currency' => 'USD',
                'period' => 'yearly',
                'text' => 'Competitive salary',
            ],
            'benefits' => ['Health insurance', 'Remote allowance'],
            'publication_date' => '2026-07-01',
            'application_deadline' => '2026-08-01',
            'expected_start_date' => '2026-09-01',
            'employment_duration' => 'permanent',
            'additional_requirements' => ['Must be based in Morocco'],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 500,
        tokensCompletion: 300,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);

    expect($output['suggestions'])->not->toBeEmpty();
    expect($output['warnings'])->toBeArray();
});

it('rejects missing title', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'company' => 'TechCorp',
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    try {
        $this->validator->validate($result);
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('invalid_ai_output')
            ->and($exception->getErrorBag()['validation_paths'])->toContain('job.title');
    }
});

it('handles empty optional fields gracefully', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);

    expect($output['warnings'])->toBeArray();
});

it('maps overview suggestions from job data', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Senior Developer',
            'company' => 'TechCorp',
            'department' => 'Engineering',
            'summary' => 'A great role.',
            'application_url' => 'https://apply.example.com',
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);

    $types = array_map(fn ($s) => $s->type, $output['suggestions']);
    expect($types)->toContain('job_title');
    expect($types)->toContain('company');
    expect($types)->toContain('department');
    expect($types)->toContain('summary');
    expect($types)->toContain('application_url');
});

it('does not create contract_type suggestion when source lacks it', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $types = array_map(fn ($s) => $s->type, $output['suggestions']);
    expect($types)->not->toContain('contract_type');
});

it('rejects placeholder labels in skills', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'required_skills' => [
                ['label' => 'N/A', 'source' => 'job_description'],
                ['label' => 'Unknown', 'source' => 'job_description'],
            ],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $skillSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'required_skill');
    expect($skillSuggestions)->toBeEmpty();
});

it('rejects empty compensation', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'compensation' => [
                'text' => '   ',
                'currency' => '',
            ],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $compSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'compensation');
    expect($compSuggestions)->toBeEmpty();
});

it('creates compensation suggestion from meaningful values', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'compensation' => [
                'salary_max' => 90000,
                'currency' => 'USD',
                'period' => 'yearly',
                'text' => 'Competitive salary up to 90,000 USD',
            ],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $compSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'compensation');
    expect($compSuggestions)->toHaveCount(1);
});

it('preserves an explicitly false travel requirement', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'travel_required' => false,
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $travelSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'travel_required');
    expect($travelSuggestions)->toHaveCount(1)
        ->and(array_values($travelSuggestions)[0]->extractedValue)->toBe(['value' => false]);
});

it('creates a travel requirement suggestion when true', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'travel_required' => true,
            'relocation_required' => false,
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $travelSugs = array_filter($output['suggestions'], fn ($s) => $s->type === 'travel_required');
    $relocationSugs = array_filter($output['suggestions'], fn ($s) => $s->type === 'relocation_required');
    expect($travelSugs)->toHaveCount(1);
    expect($relocationSugs)->toHaveCount(1)
        ->and(array_values($relocationSugs)[0]->extractedValue)->toBe(['value' => false]);
});

it('maps skill suggestions with classification', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'required_skills' => [
                ['label' => 'PHP', 'classification' => 'required', 'level' => 'advanced'],
                ['label' => 'Laravel', 'classification' => 'required', 'level' => 'advanced'],
            ],
            'preferred_skills' => [
                ['label' => 'Vue.js', 'classification' => 'preferred', 'level' => 'intermediate'],
            ],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);

    $requiredSkills = array_filter($output['suggestions'], fn ($s) => str_contains($s->type, 'required_skill'));
    $preferredSkills = array_filter($output['suggestions'], fn ($s) => str_contains($s->type, 'preferred_skill'));

    expect($requiredSkills)->toHaveCount(2);
    expect($preferredSkills)->toHaveCount(1);
});

it('creates benefit suggestions from meaningful values', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'benefits' => ['Health insurance', 'Remote work', 'Stock options'],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $benefitSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'benefit');
    expect($benefitSuggestions)->toHaveCount(3);
    $names = array_map(fn ($s) => $s->extractedValue['name'] ?? '', $benefitSuggestions);
    expect($names)->toContain('Health insurance');
    expect($names)->toContain('Remote work');
    expect($names)->toContain('Stock options');
});

it('rejects empty and whitespace-only benefits', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'benefits' => ['', '   ', 'N/A', 'Unknown'],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $benefitSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'benefit');
    expect($benefitSuggestions)->toBeEmpty();
});

it('rejects whitespace-only compensation text', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'compensation' => [
                'text' => '   ',
                'currency' => '   ',
                'salary_max' => null,
            ],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $compSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'compensation');
    expect($compSuggestions)->toBeEmpty();
});

it('passes benefit extracted_value as array with name key through data transfer', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'benefits' => ['Remote work option'],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $benefitSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'benefit');
    expect($benefitSuggestions)->toHaveCount(1);
    $benefit = array_values($benefitSuggestions)[0];
    expect($benefit->extractedValue)->toBe(['name' => 'Remote work option']);
});

it('passes compensation extracted_value as array through data transfer', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Developer',
            'compensation' => [
                'text' => 'Up to €110,000',
                'currency' => '€',
                'salary_max' => 110000,
                'period' => 'yearly',
            ],
            'responsibilities' => [],
            'required_experience' => [],
            'preferred_experience' => [],
            'education_requirements' => [],
            'required_skills' => [],
            'preferred_skills' => [],
            'languages' => [],
            'certifications' => [],
            'benefits' => [],
            'additional_requirements' => [],
        ],
        warnings: [],
        provider: 'fake',
        model: 'fake-validator-v1',
        promptVersion: '1.0.0',
        latencyMs: 100,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test_'.uniqid(),
    );

    $output = $this->validator->validate($result);
    $compSuggestions = array_filter($output['suggestions'], fn ($s) => $s->type === 'compensation');
    expect($compSuggestions)->toHaveCount(1);
    $comp = array_values($compSuggestions)[0];
    expect($comp->extractedValue['salary_max'])->toBe(110000);
    expect($comp->extractedValue['currency'])->toBe('€');
    expect($comp->extractedValue['period'])->toBe('yearly');
    expect($comp->extractedValue['text'])->toBe('Up to €110,000');
});
