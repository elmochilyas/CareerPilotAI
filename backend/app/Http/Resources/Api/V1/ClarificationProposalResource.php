<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Clarification\Data\ClarificationProposalData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClarificationProposalData */
class ClarificationProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'answer_id' => $this->answerId,
            'target' => [
                'type' => $this->targetType,
                'id' => $this->targetId,
            ],
            'field' => $this->field,
            'before_value' => $this->beforeValue,
            'after_value' => $this->afterValue,
            'status' => $this->status,
        ];
    }
}
