<?php

namespace Database\Factories;

use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchFinding> */
class MatchFindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_analysis_id' => MatchAnalysis::factory(),
            'source_type' => RequirementSourceType::JobRequirement,
            'source_id' => fake()->unique()->numberBetween(1, 1000000),
            'requirement_text' => fake()->sentence(6),
            'requirement_label' => fake()->optional()->word(),
            'importance' => MatchImportance::Required,
            'category' => null,
            'match_state' => MatchState::Gap,
            'factor' => 0.00,
            'matched_candidate_skill_id' => null,
            'evidence_refs' => null,
            'justification' => null,
            'confidence' => null,
            'classifier_source' => null,
            'display_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function required(): static
    {
        return $this->state(fn (): array => ['importance' => MatchImportance::Required]);
    }

    public function preferred(): static
    {
        return $this->state(fn (): array => ['importance' => MatchImportance::Preferred]);
    }

    public function matched(): static
    {
        return $this->state(fn (): array => ['match_state' => MatchState::Matched, 'factor' => 1.00]);
    }

    public function partial(): static
    {
        return $this->state(fn (): array => ['match_state' => MatchState::Partial, 'factor' => 0.50]);
    }

    public function gap(): static
    {
        return $this->state(fn (): array => ['match_state' => MatchState::Gap, 'factor' => 0.00]);
    }

    public function unknown(): static
    {
        return $this->state(fn (): array => ['match_state' => MatchState::Unknown, 'factor' => 0.00]);
    }
}
