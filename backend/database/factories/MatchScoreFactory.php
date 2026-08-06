<?php

namespace Database\Factories;

use App\Domain\Matching\Enums\MatchCategory;
use App\Models\MatchAnalysis;
use App\Models\MatchScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchScore> */
class MatchScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_analysis_id' => MatchAnalysis::factory(),
            'category' => MatchCategory::RequiredSkills,
            'weight' => config('matching.weights.required_skills'),
            'score' => fake()->numberBetween(0, 100),
            'achieved_points' => 0.00,
            'total_points' => 0.00,
            'has_candidate_data' => true,
        ];
    }

    public function forCategory(MatchCategory $category): static
    {
        return $this->state(fn (): array => [
            'category' => $category,
            'weight' => config("matching.weights.{$category->value}"),
        ]);
    }
}
