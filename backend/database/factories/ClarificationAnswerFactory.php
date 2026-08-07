<?php

namespace Database\Factories;

use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClarificationAnswer> */
class ClarificationAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'question_id' => ClarificationQuestion::factory(),
            'answer_type' => ClarificationAnswerType::Yes,
            'value' => 'yes',
            'acknowledged_no_evidence' => false,
            'status' => ClarificationAnswerStatus::Pending,
            'proposal_id' => null,
        ];
    }

    public function yes(): static
    {
        return $this->state(fn (): array => [
            'answer_type' => ClarificationAnswerType::Yes,
            'value' => 'yes',
            'acknowledged_no_evidence' => false,
        ]);
    }

    public function no(): static
    {
        return $this->state(fn (): array => [
            'answer_type' => ClarificationAnswerType::No,
            'value' => 'no',
            'acknowledged_no_evidence' => false,
        ]);
    }

    public function withAck(): static
    {
        return $this->state(fn (): array => [
            'answer_type' => ClarificationAnswerType::NoWithAck,
            'value' => 'no',
            'acknowledged_no_evidence' => true,
        ]);
    }

    public function text(string $value): static
    {
        return $this->state(fn (): array => [
            'answer_type' => ClarificationAnswerType::Text,
            'value' => $value,
            'acknowledged_no_evidence' => false,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationAnswerStatus::Pending]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationAnswerStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationAnswerStatus::Rejected]);
    }

    public function skipped(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationAnswerStatus::Skipped]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationAnswerStatus::Expired]);
    }
}
