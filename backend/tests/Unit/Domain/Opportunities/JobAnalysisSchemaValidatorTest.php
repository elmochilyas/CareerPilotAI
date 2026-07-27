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
