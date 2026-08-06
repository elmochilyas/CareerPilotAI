<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_analysis_id')->constrained('match_analyses')->cascadeOnDelete();
            $table->string('category', 30);
            $table->decimal('weight', 4, 3);
            $table->unsignedSmallInteger('score');
            $table->decimal('achieved_points', 8, 2);
            $table->decimal('total_points', 8, 2);
            $table->timestamps();

            $table->unique(['match_analysis_id', 'category']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE match_scores ADD CONSTRAINT match_scores_category_check CHECK (category IN ('required_skills', 'preferred_skills', 'evidence', 'experience_education', 'language_soft'))");
            DB::statement('ALTER TABLE match_scores ADD CONSTRAINT match_scores_score_check CHECK (score >= 0 AND score <= 100)');
            DB::statement('ALTER TABLE match_scores ADD CONSTRAINT match_scores_weight_check CHECK (weight >= 0 AND weight <= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_scores');
    }
};
