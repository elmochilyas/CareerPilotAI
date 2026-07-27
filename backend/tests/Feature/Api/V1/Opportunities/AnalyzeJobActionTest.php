<?php

use App\Domain\Opportunities\Actions\AnalyzeJobAction;
use App\Domain\Opportunities\Data\JobAnalysisResult;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\Contracts\JobAnalyzer;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'ingestion', 'unit');

beforeEach(function () {
    $this->user = User::factory()->create();
});

function buildValidJobAnalysisResult(): JobAnalysisResult
{
    return new JobAnalysisResult(
        schemaVersion: '1.0.0',
        job: [
            'title' => 'Senior Laravel Developer',
            'company' => 'TechCorp',
            'summary' => 'We are looking for a senior developer.',
            'work_mode' => 'hybrid',
            'contract_type' => 'full-time',
            'seniority_level' => 'senior',
            'working_hours' => '40h/week',
            'travel_required' => false,
            'relocation_required' => false,
            'responsibilities' => [
                ['text' => 'Develop and maintain Laravel applications', 'source' => 'job description'],
            ],
            'required_experience' => [
                ['summary' => 'Experience with Laravel', 'years' => 5, 'source' => 'job description'],
            ],
            'education_requirements' => [
                ['degree' => 'Bachelor', 'field' => 'Computer Science', 'required' => true],
            ],
            'required_skills' => [
                ['label' => 'Laravel', 'proficiency' => 'advanced', 'years_experience' => 5, 'source' => 'job description'],
            ],
            'languages' => [
                ['language' => 'English', 'required' => true, 'proficiency' => 'advanced', 'source' => 'job description'],
            ],
            'compensation' => [
                'salary_min' => 60000,
                'salary_max' => 90000,
                'currency' => 'USD',
                'period' => 'yearly',
            ],
            'publication_date' => '2026-07-01',
            'application_deadline' => '2026-08-01',
            'expected_start_date' => '2026-09-01',
            'employment_duration' => 'permanent',
        ],
        warnings: [],
        provider: 'test',
        model: 'test',
        promptVersion: '1.0.0',
        latencyMs: 0,
        tokensPrompt: 0,
        tokensCompletion: 0,
        responseId: 'test',
    );
}

it('transitions queued ingestion to review_ready through processing', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                return buildValidJobAnalysisResult();
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    app(AnalyzeJobAction::class)->execute($ingestion);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::ReviewReady);
});

it('persists suggestions from analysis', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                return buildValidJobAnalysisResult();
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    app(AnalyzeJobAction::class)->execute($ingestion);

    expect($ingestion->suggestions()->count())->toBeGreaterThan(0);
    expect($ingestion->suggestions()->first()->type->value)->not->toBeEmpty();
});

it('reverts to queued and throws for retryable provider timeout', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException('Provider timeout.', 'provider_timeout');
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    expect(fn () => app(AnalyzeJobAction::class)->execute($ingestion))
        ->toThrow(ConflictException::class);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued);
    expect($ingestion->failure_code)->toBeNull();
    expect($ingestion->failure_reason)->toBeNull();
});

it('reverts to queued and throws for retryable rate limit', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException('Rate limit.', 'provider_rate_limited');
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    expect(fn () => app(AnalyzeJobAction::class)->execute($ingestion))
        ->toThrow(ConflictException::class);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued);
});

it('reverts to queued and throws for retryable provider unavailable', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException('Unavailable.', 'ai_provider_unavailable');
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    expect(fn () => app(AnalyzeJobAction::class)->execute($ingestion))
        ->toThrow(ConflictException::class);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued);
});

it('marks permanent failure for invalid ai output with distinct code', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException('Invalid AI output.', 'invalid_ai_output');
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    app(AnalyzeJobAction::class)->execute($ingestion);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('invalid_ai_output');
});

it('marks permanent failure for missing provider configuration', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException('Not configured.', 'ai_provider_not_configured');
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    app(AnalyzeJobAction::class)->execute($ingestion);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('ai_provider_not_configured');
});

it('marks permanent failure for authentication failure', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException('Auth failed.', 'ai_provider_authentication_failed');
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    app(AnalyzeJobAction::class)->execute($ingestion);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('ai_provider_authentication_failed');
});

it('uses safe candidate-facing message for missing provider config', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException(
                    'OPENAI_API_KEY is not set in configuration.',
                    'ai_provider_not_configured'
                );
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    app(AnalyzeJobAction::class)->execute($ingestion);

    $ingestion->refresh();
    expect($ingestion->failure_reason)->toBe('AI analysis is currently unavailable. Please try again later.');
    expect($ingestion->failure_reason)->not->toContain('OPENAI_API_KEY');
});

it('permanent failure does not throw from action', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException('Invalid.', 'invalid_ai_output');
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    app(AnalyzeJobAction::class)->execute($ingestion);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
});

it('does not change already failed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->user->id,
        'failure_code' => 'original_code',
        'failure_reason' => 'Original failure.',
        'retry_count' => 1,
    ]);

    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                return buildValidJobAnalysisResult();
            }
        };
    });

    expect(fn () => app(AnalyzeJobAction::class)->execute($ingestion))
        ->toThrow(ConflictException::class);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Failed);
    expect($ingestion->failure_code)->toBe('original_code');
    expect($ingestion->retry_count)->toBe(1);
});

it('does not process confirmed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->confirmed()->create([
        'user_id' => $this->user->id,
    ]);

    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                return buildValidJobAnalysisResult();
            }
        };
    });

    expect(fn () => app(AnalyzeJobAction::class)->execute($ingestion))
        ->toThrow(ConflictException::class);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Confirmed);
});

it('does not process cancelled ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->cancelled()->create([
        'user_id' => $this->user->id,
    ]);

    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                return buildValidJobAnalysisResult();
            }
        };
    });

    expect(fn () => app(AnalyzeJobAction::class)->execute($ingestion))
        ->toThrow(ConflictException::class);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Cancelled);
});

it('sanitizes failure reason for permanent error with sensitive details', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException(
                    'API key sk-xxxxxxxxxxxxxxxxxxxxx is invalid',
                    'ai_provider_authentication_failed'
                );
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    app(AnalyzeJobAction::class)->execute($ingestion);

    $ingestion->refresh();
    expect($ingestion->failure_reason)
        ->not->toContain('sk-');
    expect($ingestion->failure_reason)
        ->toBe('AI analysis is currently unavailable. Please try again later.');
});

it('does not create duplicate suggestions after retryable failure and retry', function () {
    $callCount = 0;

    $this->app->bind(JobAnalyzer::class, function () use (&$callCount) {
        return new class($callCount) implements JobAnalyzer
        {
            public function __construct(private int &$callCount) {}

            public function analyze(string $description): JobAnalysisResult
            {
                $this->callCount++;
                if ($this->callCount === 1) {
                    throw new ConflictException('Timeout.', 'provider_timeout');
                }

                return buildValidJobAnalysisResult();
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    expect(fn () => app(AnalyzeJobAction::class)->execute($ingestion))
        ->toThrow(ConflictException::class);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued);

    app(AnalyzeJobAction::class)->execute($ingestion);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::ReviewReady);
    expect($ingestion->suggestions()->count())->toBeGreaterThan(0);
});

it('rolls back partial suggestions and classifies persistence failures distinctly', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                return buildValidJobAnalysisResult();
            }
        };
    });

    Event::listen(
        'eloquent.creating: '.JobOpportunitySuggestion::class,
        fn () => throw new RuntimeException('Simulated database persistence failure.'),
    );

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
    ]);

    try {
        app(AnalyzeJobAction::class)->execute($ingestion);
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('suggestion_persistence_failed')
            ->and($exception->getErrorBag()['processing_stage'])->toBe('suggestion_persistence')
            ->and($exception->getPrevious())->toBeInstanceOf(RuntimeException::class);
    }

    $ingestion->refresh();
    expect($ingestion->status)->toBe(JobIngestionStatus::Queued)
        ->and($ingestion->suggestions()->count())->toBe(0);
});

it('logs only safe diagnostic metadata for analysis failures', function () {
    $this->app->bind(JobAnalyzer::class, function () {
        return new class implements JobAnalyzer
        {
            public function analyze(string $description): JobAnalysisResult
            {
                throw new ConflictException(
                    'RAW_PROVIDER_RESPONSE must never be logged.',
                    'invalid_ai_output',
                    [
                        'processing_stage' => 'schema_validation',
                        'provider_response_received' => true,
                        'validation_paths' => ['job.title'],
                    ],
                );
            }
        };
    });

    $ingestion = JobOpportunityIngestion::factory()->queued()->create([
        'user_id' => $this->user->id,
        'source_description' => 'PRIVATE_JOB_DESCRIPTION must never be logged.',
    ]);

    Log::spy();

    app(AnalyzeJobAction::class)->execute($ingestion);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($ingestion): bool {
            $encodedContext = (string) json_encode($context);

            return $message === 'Job opportunity analysis failed'
                && $context['ingestion_id'] === $ingestion->id
                && $context['processing_stage'] === 'schema_validation'
                && $context['validation_paths'] === ['job.title']
                && ! str_contains($encodedContext, 'RAW_PROVIDER_RESPONSE')
                && ! str_contains($encodedContext, 'PRIVATE_JOB_DESCRIPTION');
        });
});
