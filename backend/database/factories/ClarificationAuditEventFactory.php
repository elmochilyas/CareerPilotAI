<?php

namespace Database\Factories;

use App\Domain\Clarification\Enums\ClarificationProposalField;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Models\CandidateSkill;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationAuditEvent;
use App\Models\ClarificationProposal;
use App\Models\MatchAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClarificationAuditEvent> */
class ClarificationAuditEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'answer_id' => ClarificationAnswer::factory(),
            'proposal_id' => ClarificationProposal::factory(),
            'user_id' => User::factory(),
            'match_analysis_id' => MatchAnalysis::factory(),
            'target_type' => ClarificationTargetType::CandidateSkill,
            'target_id' => CandidateSkill::factory(),
            'field' => ClarificationProposalField::STATE,
            'before_value' => null,
            'after_value' => ['state' => 'verified'],
            'metadata' => null,
        ];
    }

    public function forProposal(ClarificationProposal $proposal): static
    {
        return $this->state(fn (): array => [
            'proposal_id' => $proposal->id,
            'answer_id' => $proposal->answer_id,
            'target_type' => $proposal->target_type,
            'target_id' => $proposal->target_id,
            'field' => $proposal->field,
            'before_value' => $proposal->before_value,
            'after_value' => $proposal->after_value,
        ]);
    }
}
