<?php

use App\Domain\Opportunities\Data\JobAnalysisResult;
use App\Domain\Opportunities\Services\JobAnalysisSchemaValidator;
use App\Exceptions\Api\ConflictException;

uses()->group('opportunities', 'validation', 'ai-validation');

beforeEach(function () {
    $this->validator = new JobAnalysisSchemaValidator;
});

it('rejects result with non-array job', function () {
    try {
        $this->validator->validate(new JobAnalysisResult(
            schemaVersion: '1.0.0',
            job: [],
            warnings: [],
            provider: 'fake',
            model: 'test',
            promptVersion: null,
            latencyMs: null,
            tokensPrompt: null,
            tokensCompletion: null,
            responseId: null,
        ));
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('invalid_ai_output')
            ->and($exception->getErrorBag()['validation_paths'])->toContain('job.title');
    }
});

it('rejects result with missing title in job', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [],
        warnings: [],
        provider: 'fake',
        model: 'test',
        promptVersion: null,
        latencyMs: null,
        tokensPrompt: null,
        tokensCompletion: null,
        responseId: null,
    );

    try {
        $this->validator->validate($result);
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('invalid_ai_output')
            ->and($exception->getErrorBag()['validation_paths'])->toContain('job.title');
    }
});

it('rejects result with empty string title', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: ['title' => '   '],
        warnings: [],
        provider: 'fake',
        model: 'test',
        promptVersion: null,
        latencyMs: null,
        tokensPrompt: null,
        tokensCompletion: null,
        responseId: null,
    );

    try {
        $this->validator->validate($result);
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('invalid_ai_output')
            ->and($exception->getErrorBag()['validation_paths'])->toContain('job.title');
    }
});

it('rejects result with non-string title', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: ['title' => 12345],
        warnings: [],
        provider: 'fake',
        model: 'test',
        promptVersion: null,
        latencyMs: null,
        tokensPrompt: null,
        tokensCompletion: null,
        responseId: null,
    );

    try {
        $this->validator->validate($result);
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('invalid_ai_output')
            ->and($exception->getErrorBag()['validation_paths'])->toContain('job.title');
    }
});

it('handles null warnings gracefully', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: ['title' => 'Senior Laravel Developer'],
        warnings: ['Parsing issue detected'],
        provider: 'fake',
        model: 'test',
        promptVersion: null,
        latencyMs: null,
        tokensPrompt: null,
        tokensCompletion: null,
        responseId: null,
    );

    $validated = $this->validator->validate($result);
    expect($validated['warnings'])->toBe(['Parsing issue detected']);
});

it('validates valid result successfully', function () {
    $result = new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Senior Laravel Developer',
            'company' => 'TechCorp',
            'summary' => 'We need a Laravel expert.',
            'location' => ['city' => 'Casablanca', 'country' => 'Morocco'],
            'work_mode' => 'hybrid',
            'contract_type' => 'full-time',
            'seniority_level' => 'senior',
            'responsibilities' => [
                ['text' => 'Develop Laravel applications'],
            ],
            'required_skills' => [
                ['label' => 'PHP', 'proficiency' => 'advanced'],
            ],
            'compensation' => [
                'salary_min' => 50000,
                'salary_max' => 80000,
                'currency' => 'USD',
                'period' => 'yearly',
            ],
        ],
        warnings: [],
        provider: 'fake',
        model: 'test',
        promptVersion: null,
        latencyMs: null,
        tokensPrompt: null,
        tokensCompletion: null,
        responseId: null,
    );

    $validated = $this->validator->validate($result);
    expect($validated['suggestions'])->not->toBeEmpty();

    $types = array_map(fn ($s) => $s->type, $validated['suggestions']);
    expect($types)->toContain('job_title');
    expect($types)->toContain('company');
    expect($types)->toContain('summary');
    expect($types)->toContain('city');
    expect($types)->toContain('country');
    expect($types)->toContain('work_mode');
    expect($types)->toContain('contract_type');
    expect($types)->toContain('seniority_level');
});
