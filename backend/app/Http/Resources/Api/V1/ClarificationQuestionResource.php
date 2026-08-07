<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Clarification\Data\ClarificationSessionQuestionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClarificationSessionQuestionData */
class ClarificationQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_no' => $this->questionNo,
            'question_type' => $this->questionType,
            'prompt' => $this->prompt,
            'detail' => $this->detail,
            'template_key' => $this->templateKey,
            'options' => $this->options,
            'unit' => $this->unit,
            'status' => $this->status,
            'requirement' => [
                'text' => $this->requirementText,
                'label' => $this->requirementLabel,
            ],
            'answer' => $this->answer !== null
                ? new ClarificationAnswerResource($this->answer)
                : null,
        ];
    }
}
