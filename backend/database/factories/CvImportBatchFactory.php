<?php

namespace Database\Factories;

use App\Domain\CvIngestion\Enums\CvImportBatchStatus;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CvImportBatch> */
class CvImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cv_document_id' => CvDocument::factory(),
            'user_id' => User::factory(),
            'status' => CvImportBatchStatus::Pending->value,
            'idempotency_key' => hash('sha256', fake()->uuid()),
        ];
    }

    public function applied(): static
    {
        return $this->state([
            'status' => CvImportBatchStatus::Applied->value,
            'imported_at' => fake()->dateTimeThisMonth(),
            'summary' => [
                'fields_updated' => 2,
                'items_created' => 3,
                'items_updated' => 1,
                'skills_added' => 4,
                'total_accepted' => 10,
            ],
        ]);
    }
}
