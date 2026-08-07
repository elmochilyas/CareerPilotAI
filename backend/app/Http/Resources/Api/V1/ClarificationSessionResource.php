<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Clarification\Data\ClarificationSessionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClarificationSessionData */
class ClarificationSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'analysis_id' => $this->analysisId,
            'questions' => ClarificationQuestionResource::collection($this->questions),
            'progress' => [
                'answered' => $this->answered,
                'total' => $this->total,
            ],
            'generable_count' => $this->generableCount,
        ];
    }
}
