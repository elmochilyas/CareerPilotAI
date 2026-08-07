<?php

namespace Database\Factories;

use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Domain\Clarification\Enums\ClarificationQuestionType;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClarificationQuestion> */
class ClarificationQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_analysis_id' => MatchAnalysis::factory(),
            'match_finding_id' => MatchFinding::factory(),
            'question_no' => fake()->numberBetween(1, 3),
            'question_type' => ClarificationQuestionType::YesNo,
            'prompt' => fake()->sentence(8),
            'detail' => fake()->optional()->sentence(10),
            'template_key' => 'skill_missing_required',
            'options_json' => null,
            'unit' => null,
            'status' => ClarificationQuestionStatus::Pending,
            'ai_metadata' => null,
        ];
    }

    public function yesNo(): static
    {
        return $this->state(fn (): array => ['question_type' => ClarificationQuestionType::YesNo]);
    }

    public function yesNoWithDetails(): static
    {
        return $this->state(fn (): array => ['question_type' => ClarificationQuestionType::YesNoWithDetails]);
    }

    public function text(): static
    {
        return $this->state(fn (): array => ['question_type' => ClarificationQuestionType::Text]);
    }

    public function select(): static
    {
        return $this->state(fn (): array => [
            'question_type' => ClarificationQuestionType::Select,
            'options_json' => ['Less than 1 year', '1-2 years', '3-5 years', 'More than 5 years'],
        ]);
    }

    public function number(): static
    {
        return $this->state(fn (): array => [
            'question_type' => ClarificationQuestionType::Number,
            'unit' => 'years',
        ]);
    }

    public function answered(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationQuestionStatus::Answered]);
    }

    public function skipped(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationQuestionStatus::Skipped]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => ClarificationQuestionStatus::Expired]);
    }
}
