<?php

namespace App\Domain\Clarification\Data;

final readonly class ClarificationAssistantResult
{
    /**
     * @param  list<AssistantRewordedQuestion>  $questions
     */
    public function __construct(
        public string $schemaVersion,
        public array $questions,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public ?int $latencyMs,
        public ?int $tokensPrompt,
        public ?int $tokensCompletion,
        public ?string $responseId,
    ) {}
}
