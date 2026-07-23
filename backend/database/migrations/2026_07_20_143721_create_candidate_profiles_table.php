<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();
            $table->text('professional_summary')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('github_url', 500)->nullable();
            $table->string('portfolio_url', 500)->nullable();
            $table->string('availability_status', 30)->nullable();
            $table->json('target_roles')->nullable();
            $table->json('preferred_locations')->nullable();
            $table->string('work_mode', 30)->nullable();
            $table->json('contract_types')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->json('languages')->nullable();
            $table->decimal('profile_completion', 5, 2)->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE candidate_profiles ADD CONSTRAINT candidate_profiles_salary_range_check CHECK (salary_max IS NULL OR salary_min IS NULL OR salary_max >= salary_min)');
        DB::statement('ALTER TABLE candidate_profiles ADD CONSTRAINT candidate_profiles_completion_check CHECK (profile_completion BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_profiles');
    }
};
