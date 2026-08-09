<?php

use App\Domain\Clarification\Data\AssistantQuestion;
use App\Domain\Clarification\Data\AssistantRewordedQuestion;
use App\Domain\Clarification\Data\ClarificationAssistantRequest;
use App\Domain\Clarification\Data\ClarificationAssistantResult;
use App\Domain\Clarification\Enums\ClarificationQuestionType;
use App\Domain\Clarification\Exceptions\ClarificationAssistantException;
use App\Domain\Clarification\Services\ClarificationAssistantSchemaValidator;

function assistantValidatorRequest(array $overrides = []): ClarificationAssistantRequest
{
    return new ClarificationAssistantRequest(
        questions: $overrides['questions'] ?? [
            new AssistantQuestion(
                ref: '101',
                questionType: ClarificationQuestionType::YesNoWithDetails,
                prompt: 'Do you have experience with Docker?',
                requirement: 'Docker',
                evidenceBasis: 'Docker is required for this role.',
            ),
            new AssistantQuestion(
                ref: '202',
                questionType: ClarificationQuestionType::YesNoWithDetails,
                prompt: 'Do you have experience with Redis?',
                requirement: 'Redis',
                evidenceBasis: 'Redis is required for this role.',
            ),
        ],
        requestId: $overrides['requestId'] ?? 'req_assistant_validator_test',
    );
}

function assistantValidatorResult(array $questions, array $overrides = []): ClarificationAssistantResult
{
    return new ClarificationAssistantResult(
        schemaVersion: $overrides['schema_version'] ?? '1.0.0',
        questions: $questions,
        provider: 'openai',
        model: 'gpt-4o-mini',
        promptVersion: '1.0.0',
        latencyMs: 120,
        tokensPrompt: 500,
        tokensCompletion: 200,
        responseId: null,
    );
}

function assistantValidatorQuestion(string $ref, string $rewordedPrompt = '[Reworded] Prompt.'): AssistantRewordedQuestion
{
    return new AssistantRewordedQuestion(
        ref: $ref,
        rewordedPrompt: $rewordedPrompt,
    );
}

function assistantValidatorValidate(ClarificationAssistantResult $result, ?ClarificationAssistantRequest $request = null): void
{
    app(ClarificationAssistantSchemaValidator::class)->validate($result, $request ?? assistantValidatorRequest());
}

function assistantValidatorExpectFailure(Closure $callback, string $needle): void
{
    try {
        $callback();
        throw new Exception('Expected ClarificationAssistantException was not thrown.');
    } catch (ClarificationAssistantException $exception) {
        expect($exception->problemCode)->toBe('ai_assistant_schema_invalid')
            ->and(implode(' | ', $exception->errors))->toContain($needle);
    }
}

it('accepts a valid reordered question set covering every requested question', function () {
    expect(fn () => assistantValidatorValidate(assistantValidatorResult([
        assistantValidatorQuestion('202', '[Reworded] Tell us about Redis.'),
        assistantValidatorQuestion('101', '[Reworded] Tell us about Docker.'),
    ])))->not->toThrow(ClarificationAssistantException::class);
});

it('rejects an unexpected schema version', function () {
    $result = assistantValidatorResult([assistantValidatorQuestion('101')], ['schema_version' => '9.9.9']);

    assistantValidatorExpectFailure(fn () => assistantValidatorValidate($result), 'schema_version');
});

it('rejects a question count that does not match the request', function () {
    assistantValidatorExpectFailure(
        fn () => assistantValidatorValidate(assistantValidatorResult([
            assistantValidatorQuestion('101'),
        ])),
        'questions count',
    );
});

it('rejects a duplicate question for the same ref', function () {
    assistantValidatorExpectFailure(
        fn () => assistantValidatorValidate(assistantValidatorResult([
            assistantValidatorQuestion('101'),
            assistantValidatorQuestion('101'),
        ])),
        'duplicate question',
    );
});

it('rejects a question for an undeclared ref', function () {
    assistantValidatorExpectFailure(
        fn () => assistantValidatorValidate(assistantValidatorResult([
            assistantValidatorQuestion('101'),
            assistantValidatorQuestion('404'),
        ])),
        'not a requested question',
    );
});

it('rejects an empty reworded prompt', function () {
    assistantValidatorExpectFailure(
        fn () => assistantValidatorValidate(assistantValidatorResult([
            assistantValidatorQuestion('101', '   '),
            assistantValidatorQuestion('202'),
        ])),
        'must not be empty',
    );
});

it('rejects an over-length reworded prompt', function () {
    assistantValidatorExpectFailure(
        fn () => assistantValidatorValidate(assistantValidatorResult([
            assistantValidatorQuestion('101', str_repeat('x', 501)),
            assistantValidatorQuestion('202'),
        ])),
        'exceeds maximum length',
    );
});
