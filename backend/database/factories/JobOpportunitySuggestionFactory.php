<?php

namespace Database\Factories;

use App\Domain\Opportunities\Enums\ReviewDecision;
use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Domain\Opportunities\Enums\SuggestionType;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobOpportunitySuggestion> */
class JobOpportunitySuggestionFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement([
            SuggestionType::JobTitle,
            SuggestionType::Company,
            SuggestionType::Summary,
            SuggestionType::SeniorityLevel,
            SuggestionType::RequiredSkill,
            SuggestionType::PreferredSkill,
            SuggestionType::Responsibility,
            SuggestionType::RequiredExperience,
            SuggestionType::Education,
            SuggestionType::Certification,
            SuggestionType::Language,
            SuggestionType::Compensation,
        ]);

        $extractedValue = match ($type) {
            SuggestionType::JobTitle => ['value' => fake()->jobTitle()],
            SuggestionType::Company => ['value' => fake()->company()],
            SuggestionType::Summary => ['value' => fake()->paragraph()],
            SuggestionType::SeniorityLevel => ['value' => fake()->randomElement(['junior', 'mid', 'senior'])],
            SuggestionType::RequiredSkill, SuggestionType::PreferredSkill => [
                'label' => fake()->word(),
                'proficiency' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
                'years_experience' => fake()->randomFloat(1, 0, 10),
            ],
            SuggestionType::Responsibility => [
                'text' => fake()->sentence(),
            ],
            SuggestionType::RequiredExperience => [
                'years' => fake()->randomFloat(1, 0, 10),
                'summary' => fake()->sentence(),
            ],
            SuggestionType::Education => [
                'degree' => fake()->randomElement(['Bachelor', 'Master', 'PhD']),
                'field' => fake()->word(),
            ],
            SuggestionType::Certification => [
                'name' => fake()->sentence(2),
            ],
            SuggestionType::Language => [
                'language' => fake()->languageCode(),
                'proficiency' => fake()->randomElement(['beginner', 'intermediate', 'advanced', 'native']),
            ],
            SuggestionType::Compensation => [
                'salary_min' => fake()->numberBetween(30000, 80000),
                'salary_max' => fake()->numberBetween(80001, 150000),
                'currency' => 'USD',
                'period' => 'yearly',
            ],
            default => throw new \LogicException('Unsupported suggestion factory type.'),
        };

        $groupKey = match ($type) {
            SuggestionType::JobTitle,
            SuggestionType::Company,
            SuggestionType::Summary => 'overview',
            SuggestionType::SeniorityLevel => 'work_details',
            SuggestionType::RequiredSkill => 'required_skills',
            SuggestionType::PreferredSkill => 'preferred_skills',
            SuggestionType::Responsibility => 'responsibilities',
            SuggestionType::RequiredExperience => 'experience',
            SuggestionType::Education => 'education',
            SuggestionType::Certification,
            SuggestionType::Language => 'languages_certifications',
            SuggestionType::Compensation => 'compensation',
        };

        return [
            'ingestion_id' => JobOpportunityIngestion::factory(),
            'type' => $type->value,
            'group_key' => $groupKey,
            'field' => $type->value,
            'extracted_value' => $extractedValue,
            'review_decision' => ReviewDecision::Pending,
            'source_evidence' => fake()->optional()->sentence(),
            'schema_version' => '1.0.0',
            'version' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(['review_decision' => ReviewDecision::Pending]);
    }

    public function accepted(): static
    {
        return $this->state([
            'review_decision' => ReviewDecision::Accepted,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'review_decision' => ReviewDecision::Rejected,
            'reviewed_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'review_decision' => ReviewDecision::Resolved,
            'resolution' => SkillResolutionState::Exact,
            'reviewed_at' => now(),
        ]);
    }
}
