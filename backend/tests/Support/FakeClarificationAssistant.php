<?php

namespace Tests\Support;

use App\Domain\Clarification\Data\AssistantRewordedQuestion;
use App\Domain\Clarification\Data\ClarificationAssistantRequest;
use App\Domain\Clarification\Data\ClarificationAssistantResult;
use App\Domain\Clarification\Exceptions\ClarificationAssistantException;
use App\Domain\Clarification\Services\Contracts\ClarificationAssistant;

class FakeClarificationAssistant implements ClarificationAssistant
{
    public const MODE_SUCCESS = 'success';

    public const MODE_MALFORMED = 'malformed';

    public const MODE_INVALID_SCHEMA = 'invalid_schema';

    public const MODE_PROVIDER_FAILURE = 'provider_failure';

    public function __construct(
        public string $mode = self::MODE_SUCCESS,
        public bool $reverseOrder = false,
    ) {}

    public function rankAndReword(ClarificationAssistantRequest $request): ClarificationAssistantResult
    {
        return match ($this->mode) {
            self::MODE_MALFORMED => throw new ClarificationAssistantException(
                'Clarification assistant provider returned an invalid response.',
                'ai_assistant_malformed_response',
            ),
            self::MODE_INVALID_SCHEMA => throw new ClarificationAssistantException(
                'Clarification assistant output did not pass schema and business validation.',
                'ai_assistant_schema_invalid',
                ['questions.0'],
            ),
            self::MODE_PROVIDER_FAILURE => throw new ClarificationAssistantException(
                'Clarification assistant provider is temporarily unavailable.',
                'ai_assistant_unavailable',
            ),
            default => new ClarificationAssistantResult(
                schemaVersion: '1.0.0',
                questions: $this->buildQuestions($request),
                provider: 'fake',
                model: 'fake-assistant-v1',
                promptVersion: '1.0.0',
                latencyMs: 100,
                tokensPrompt: 200,
                tokensCompletion: 80,
                responseId: 'fake_'.uniqid(),
            ),
        };
    }

    /**
     * @return list<AssistantRewordedQuestion>
     */
    private function buildQuestions(ClarificationAssistantRequest $request): array
    {
        $questions = array_map(
            static fn (mixed $question): AssistantRewordedQuestion => new AssistantRewordedQuestion(
                ref: $question->ref,
                rewordedPrompt: '[Reworded] '.$question->prompt,
            ),
            $request->questions,
        );

        return $this->reverseOrder ? array_reverse($questions) : $questions;
    }
}
