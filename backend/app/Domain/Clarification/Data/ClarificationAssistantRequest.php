<?php

namespace App\Domain\Clarification\Data;

final readonly class ClarificationAssistantRequest
{
    /**
     * @param  list<AssistantQuestion>  $questions
     */
    public function __construct(
        public array $questions,
        public ?string $requestId = null,
    ) {}
}
