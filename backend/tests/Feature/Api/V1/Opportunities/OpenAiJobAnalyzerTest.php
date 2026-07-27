<?php

use App\Domain\Opportunities\Services\JobAnalysisAgent;
use App\Domain\Opportunities\Services\OpenAiJobAnalyzer;
use App\Exceptions\Api\ConflictException;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\Exceptions\RateLimitedException;

it('throws ai_provider_not_configured when API key is empty', function () {
    Config::set('ai.providers.openai.key', '');

    $analyzer = app(OpenAiJobAnalyzer::class);

    try {
        $analyzer->analyze('Some job description');
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $e) {
        expect($e->getErrorCode())->toBe('ai_provider_not_configured');
    }
});

it('throws ai_provider_not_configured when API key is null', function () {
    Config::set('ai.providers.openai.key', null);

    $analyzer = app(OpenAiJobAnalyzer::class);

    try {
        $analyzer->analyze('Some job description');
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $e) {
        expect($e->getErrorCode())->toBe('ai_provider_not_configured');
    }
});

it('does not expose provider details in exception message', function () {
    Config::set('ai.providers.openai.key', '');

    $analyzer = app(OpenAiJobAnalyzer::class);

    try {
        $analyzer->analyze('Some job description');
    } catch (ConflictException $e) {
        expect($e->getErrorCode())->toBe('ai_provider_not_configured');
        expect($e->getMessage())->not->toContain('OPENAI_API_KEY');
        expect($e->getMessage())->not->toContain('openai');
        expect($e->getMessage())->toBe('AI analysis is currently unavailable. Please try again later.');
    }
});

it('maps the installed SDK structured response without obsolete response methods', function () {
    Config::set('ai.providers.openai.key', 'test-key');
    JobAnalysisAgent::fake([[
        'schema_version' => '1.0.0',
        'job' => [
            'title' => 'Backend Developer',
            'required_skills' => [
                ['label' => 'PHP', 'source' => 'Experience with PHP'],
            ],
        ],
        'warnings' => [],
    ]])->preventStrayPrompts();

    $result = app(OpenAiJobAnalyzer::class)->analyze(
        'Backend Developer role requiring experience with PHP.',
    );

    expect($result->schemaVersion)->toBe('1.0.0')
        ->and($result->job['title'])->toBe('Backend Developer')
        ->and($result->provider)->toBe('openai')
        ->and($result->model)->toBe('gpt-4o-mini');
});

it('uses invalid_ai_output only after a structured provider response is received', function () {
    Config::set('ai.providers.openai.key', 'test-key');
    JobAnalysisAgent::fake([[
        'schema_version' => '1.0.0',
        'warnings' => [],
    ]])->preventStrayPrompts();

    try {
        app(OpenAiJobAnalyzer::class)->analyze('Backend Developer role.');
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('invalid_ai_output')
            ->and($exception->getErrorBag()['provider_response_received'])->toBeTrue()
            ->and($exception->getErrorBag()['validation_paths'])->toBe(['job']);
    }
});

it('classifies provider rate limits without calling them invalid output', function () {
    Config::set('ai.providers.openai.key', 'test-key');
    JobAnalysisAgent::fake(
        fn () => throw RateLimitedException::forProvider('openai'),
    )->preventStrayPrompts();

    try {
        app(OpenAiJobAnalyzer::class)->analyze('Backend Developer role.');
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('ai_provider_rate_limited')
            ->and($exception->getPrevious())->toBeInstanceOf(RateLimitedException::class)
            ->and($exception->getErrorBag()['provider_response_received'])->toBeFalse();
    }
});

it('classifies provider timeouts without calling them invalid output', function () {
    Config::set('ai.providers.openai.key', 'test-key');
    JobAnalysisAgent::fake(
        fn () => throw new ConnectionException('Connection timed out.'),
    )->preventStrayPrompts();

    try {
        app(OpenAiJobAnalyzer::class)->analyze('Backend Developer role.');
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('ai_provider_timeout')
            ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);
    }
});

it('classifies provider authentication failures without exposing provider details', function () {
    Config::set('ai.providers.openai.key', 'test-key');
    $requestException = new RequestException(new Response(new PsrResponse(401)));
    JobAnalysisAgent::fake(
        fn () => throw $requestException,
    )->preventStrayPrompts();

    try {
        app(OpenAiJobAnalyzer::class)->analyze('Backend Developer role.');
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('ai_provider_authentication_failed')
            ->and($exception->getMessage())->not->toContain('401')
            ->and($exception->getMessage())->not->toContain('key')
            ->and($exception->getPrevious())->toBe($requestException);
    }
});

it('classifies local runtime errors as unexpected processing failures', function () {
    Config::set('ai.providers.openai.key', 'test-key');
    JobAnalysisAgent::fake(
        fn () => throw new Error('Local mapper failure.'),
    )->preventStrayPrompts();

    try {
        app(OpenAiJobAnalyzer::class)->analyze('Backend Developer role.');
        $this->fail('Expected ConflictException was not thrown.');
    } catch (ConflictException $exception) {
        expect($exception->getErrorCode())->toBe('unexpected_processing_failure')
            ->and($exception->getPrevious())->toBeInstanceOf(Throwable::class)
            ->and($exception->getErrorBag()['provider_response_received'])->toBeFalse();
    }
});
