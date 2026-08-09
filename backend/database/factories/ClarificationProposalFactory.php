<?php

namespace Database\Factories;

use App\Domain\Clarification\Enums\ClarificationProposalField;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Models\CandidateSkill;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClarificationProposal> */
class ClarificationProposalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'answer_id' => ClarificationAnswer::factory(),
            'target_type' => ClarificationTargetType::CandidateSkill,
            'target_id' => CandidateSkill::factory(),
            'field' => ClarificationProposalField::STATE,
            'before_value' => null,
            'after_value' => ['state' => 'verified'],
            'status' => ClarificationProposalStatus::Proposed,
        ];
    }

    public function skillState(string $from, string $to): static
    {
        return $this->state(fn (): array => [
            'target_type' => ClarificationTargetType::CandidateSkill,
            'field' => ClarificationProposalField::STATE,
            'before_value' => ['state' => $from],
            'after_value' => ['state' => $to],
        ]);
    }

    public function skillEvidence(array $evidence): static
    {
        return $this->state(fn (): array => [
            'target_type' => ClarificationTargetType::CandidateSkill,
            'field' => ClarificationProposalField::EVIDENCE,
            'before_value' => null,
            'after_value' => ['evidence' => $evidence],
        ]);
    }

    public function candidateSkill(array $afterValue): static
    {
        return $this->state(fn (): array => [
            'target_type' => ClarificationTargetType::CandidateSkill,
            'target_id' => null,
            'field' => ClarificationProposalField::STATE,
            'before_value' => null,
            'after_value' => $afterValue,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationProposalStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationProposalStatus::Rejected]);
    }

    public function skipped(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationProposalStatus::Skipped]);
    }
}
