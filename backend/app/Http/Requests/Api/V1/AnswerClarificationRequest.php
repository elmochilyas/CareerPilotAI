<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Clarification\Enums\ClarificationAnswerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnswerClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $answerType = $this->input('answer_type');
        $acknowledgedNoEvidence = $this->boolean('acknowledged_no_evidence');

        if ($answerType === ClarificationAnswerType::Number->value) {
            $valueRules = ['required', 'numeric', 'min:0', 'max:100'];
        } elseif ($answerType === ClarificationAnswerType::SelectOption->value) {
            $valueRules = ['required', 'string', 'max:200'];
        } elseif ($answerType === ClarificationAnswerType::Yes->value && ! $acknowledgedNoEvidence) {
            $valueRules = ['required', 'string', 'max:2000', 'url'];
        } else {
            // no, no_with_ack, and yes with a no-evidence acknowledgement carry no value.
            $valueRules = ['nullable', 'string', 'max:2000'];
        }

        return [
            'answer_type' => ['required', Rule::enum(ClarificationAnswerType::class)],
            'value' => $valueRules,
            'acknowledged_no_evidence' => ['sometimes', 'boolean'],
        ];
    }
}
