<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained('skills')->restrictOnDelete();
            $table->string('state', 30)->default('claimed');
            $table->string('proficiency_level', 30);
            $table->decimal('years_experience', 4, 1)->nullable();
            $table->date('last_used_at')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'skill_id']);
            $table->index(['candidate_profile_id', 'state']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE candidate_skills ADD CONSTRAINT candidate_skills_state_check CHECK (state IN ('claimed', 'verified', 'learning', 'rejected', 'archived'))");
            DB::statement("ALTER TABLE candidate_skills ADD CONSTRAINT candidate_skills_proficiency_level_check CHECK (proficiency_level IN ('beginner', 'elementary', 'intermediate', 'advanced', 'expert'))");
            DB::statement('ALTER TABLE candidate_skills ADD CONSTRAINT candidate_skills_years_experience_check CHECK (years_experience IS NULL OR years_experience >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_skills');
    }
};
