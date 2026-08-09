<?php

namespace App\Domain\Clarification\Data;

final readonly class AssistantRewordedQuestion
{
    public function __construct(
        public string $ref,
        public string $rewordedPrompt,
    ) {}
}
