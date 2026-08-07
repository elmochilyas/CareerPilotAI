<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Clarification\Data\ClarificationAnswerData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClarificationAnswerData */
class ClarificationAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->questionId,
            'answer_type' => $this->answerType,
            'value' => $this->value,
            'acknowledged_no_evidence' => $this->acknowledgedNoEvidence,
            'status' => $this->status,
            'proposal' => $this->proposal !== null
                ? new ClarificationProposalResource($this->proposal)
                : null,
        ];
    }
}
