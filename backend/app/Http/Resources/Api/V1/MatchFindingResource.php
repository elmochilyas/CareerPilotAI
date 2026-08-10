<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Matching\Data\MatchFindingResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchFindingResult */
class MatchFindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'source_type' => $this->sourceType->value,
            'source_id' => $this->sourceId,
            'requirement_text' => $this->requirementText,
            'requirement_label' => $this->requirementLabel,
            'importance' => $this->importance->value,
            'category' => $this->category,
            'match_state' => $this->matchState->value,
            'factor' => $this->factor,
            'matched_candidate_skill_id' => $this->matchedCandidateSkillId,
            'evidence_refs' => $this->evidenceRefs,
            'justification' => $this->justification,
            'confidence' => $this->confidence,
            'classifier_source' => $this->classifierSource,
            'display_order' => $this->displayOrder,
            'tailoring_relevance' => $this->tailoringRelevance,
        ];
    }
}
