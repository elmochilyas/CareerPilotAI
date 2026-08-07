<?php

namespace App\Domain\Clarification\Data;

use App\Models\ClarificationQuestion;

final class ClarificationSessionQuestionData
{
    public function __construct(
        public readonly int $id,
        public readonly int $questionNo,
        public readonly string $questionType,
        public readonly string $prompt,
        public readonly ?string $detail,
        public readonly string $templateKey,
        public readonly ?array $options,
        public readonly ?string $unit,
        public readonly string $status,
        public readonly ?string $requirementText,
        public readonly ?string $requirementLabel,
        public readonly ?ClarificationAnswerData $answer,
    ) {}

    public static function fromModel(ClarificationQuestion $question): self
    {
        $finding = $question->matchFinding;

        return new self(
            id: $question->id,
            questionNo: $question->question_no,
            questionType: $question->question_type->value,
            prompt: $question->prompt,
            detail: $question->detail,
            templateKey: $question->template_key,
            options: $question->options_json,
            unit: $question->unit,
            status: $question->status->value,
            requirementText: $finding?->requirement_text,
            requirementLabel: $finding?->requirement_label,
            answer: $question->answer !== null
                ? ClarificationAnswerData::fromModel($question->answer)
                : null,
        );
    }
}
