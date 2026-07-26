<?php

namespace Database\Factories;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Models\CvDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CvDocument> */
class CvDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => 'cv.'.fake()->randomElement(['pdf', 'docx']),
            'stored_path' => 'cv-ingestion/'.fake()->uuid().'.'.fake()->randomElement(['pdf', 'docx']),
            'stored_name' => fake()->uuid().'.'.fake()->randomElement(['pdf', 'docx']),
            'mime_type' => fake()->randomElement(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),
            'size' => fake()->numberBetween(10000, 5000000),
            'checksum' => hash('sha256', fake()->uuid()),
            'status' => CvDocumentStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => CvDocumentStatus::Pending]);
    }

    public function queued(): static
    {
        return $this->state(['status' => CvDocumentStatus::Queued]);
    }

    public function readyForReview(): static
    {
        return $this->state(['status' => CvDocumentStatus::ReadyForReview]);
    }

    public function imported(): static
    {
        return $this->state(['status' => CvDocumentStatus::Imported]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => CvDocumentStatus::Failed,
            'failure_reason' => fake()->sentence(),
            'failure_code' => 'file_corrupt',
        ]);
    }

    public function deleted(): static
    {
        return $this->state(['status' => CvDocumentStatus::Deleted]);
    }
}
