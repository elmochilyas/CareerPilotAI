<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Matching\Data\MatchScoreComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchScoreComponent */
class MatchScoreComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'category' => $this->category->value,
            'weight' => $this->weight,
            'score' => $this->score,
            'achieved_points' => $this->achievedPoints,
            'total_points' => $this->totalPoints,
            'has_candidate_data' => $this->hasCandidateData,
        ];
    }
}
