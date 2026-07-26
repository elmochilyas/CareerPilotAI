<?php

namespace Database\Factories;

use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionType;
use App\Domain\CvIngestion\Enums\ExtractionMethod;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CvSuggestion> */
class CvSuggestionFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(CvSuggestionType::cases());

        $suggestedValue = match ($type) {
            CvSuggestionType::Headline => ['value' => fake()->jobTitle()],
            CvSuggestionType::Summary => ['value' => fake()->paragraph()],
            CvSuggestionType::BasicInformation => ['value' => fake()->name()],
            CvSuggestionType::SocialLink => ['type' => 'linkedin', 'url' => fake()->url()],
            CvSuggestionType::Experience => [
                'title' => fake()->jobTitle(),
                'organization' => fake()->company(),
                'location' => fake()->city(),
                'start_date' => '2024-01',
                'end_date' => null,
                'is_current' => true,
                'description' => fake()->paragraph(),
                'technologies' => [fake()->word(), fake()->word()],
            ],
            CvSuggestionType::Project => [
                'name' => fake()->sentence(2),
                'role' => fake()->jobTitle(),
                'description' => fake()->paragraph(),
                'technologies' => [fake()->word()],
                'url' => null,
                'start_date' => null,
                'end_date' => null,
                'is_current' => false,
            ],
            CvSuggestionType::Education => [
                'degree' => fake()->word().' Degree',
                'field_of_study' => fake()->word(),
                'institution' => fake()->company(),
                'location' => fake()->city(),
                'start_date' => '2022-09',
                'end_date' => null,
                'is_current' => true,
                'description' => null,
            ],
            CvSuggestionType::Language => ['language' => fake()->languageCode(), 'proficiency' => 'Advanced'],
            CvSuggestionType::Skill => ['name' => fake()->word(), 'category' => fake()->word()],
            CvSuggestionType::Certification => ['name' => fake()->sentence(2), 'issuer' => fake()->company()],
            default => ['value' => fake()->word()],
        };

        return [
            'cv_document_id' => CvDocument::factory(),
            'cv_processing_run_id' => CvProcessingRun::factory(),
            'type' => $type->value,
            'suggested_value' => $suggestedValue,
            'extraction_method' => ExtractionMethod::AiExtraction->value,
            'schema_version' => '1.1.0',
            'review_status' => CvSuggestionReviewStatus::Pending->value,
        ];
    }

    public function pending(): static
    {
        return $this->state(['review_status' => CvSuggestionReviewStatus::Pending->value]);
    }

    public function accepted(): static
    {
        return $this->state(['review_status' => CvSuggestionReviewStatus::Accepted->value]);
    }
}
