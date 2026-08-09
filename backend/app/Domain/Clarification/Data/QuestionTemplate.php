<?php

namespace App\Domain\Clarification\Data;

use App\Domain\Clarification\Enums\ClarificationQuestionType;

final readonly class QuestionTemplate
{
    /**
     * @param  list<string>|null  $options
     */
    public function __construct(
        public string $templateKey,
        public string $version,
        public ClarificationQuestionType $questionType,
        public string $prompt,
        public ?string $detail,
        public ?array $options,
        public ?string $unit,
    ) {}

    public function withPrompt(string $prompt): self
    {
        return new self(
            templateKey: $this->templateKey,
            version: $this->version,
            questionType: $this->questionType,
            prompt: $prompt,
            detail: $this->detail,
            options: $this->options,
            unit: $this->unit,
        );
    }
}
