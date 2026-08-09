<?php

namespace App\Domain\Clarification\Data;

use App\Domain\Clarification\Enums\ClarificationQuestionType;

final readonly class AssistantQuestion
{
    public function __construct(
        public string $ref,
        public ClarificationQuestionType $questionType,
        public string $prompt,
        public ?string $requirement,
        public ?string $evidenceBasis,
    ) {}
}
