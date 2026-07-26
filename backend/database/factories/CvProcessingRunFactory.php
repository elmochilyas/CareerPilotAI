<?php

namespace Database\Factories;

use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CvProcessingRun> */
class CvProcessingRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cv_document_id' => CvDocument::factory(),
            'status' => 'completed',
            'pipeline_version' => '1.0.0',
            'idempotency_key' => hash('sha256', fake()->uuid()),
            'started_at' => fake()->dateTimeThisMonth(),
            'completed_at' => fake()->dateTimeThisMonth(),
        ];
    }
}
