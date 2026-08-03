<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained();
            $table->foreignId('ingestion_id')->nullable()->constrained('job_opportunity_ingestions');
            $table->foreignId('company_id')->nullable()->constrained();
            $table->string('title', 255);
            $table->string('company_name', 255)->nullable();
            $table->string('department', 255)->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->text('summary')->nullable();
            $table->string('application_url', 500)->nullable();
            $table->string('personal_label', 255)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('work_mode', 30)->nullable();
            $table->string('contract_type', 30)->nullable();
            $table->string('seniority_level', 50)->nullable();
            $table->string('working_hours', 100)->nullable();
            $table->boolean('travel_required')->nullable();
            $table->boolean('relocation_required')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->string('salary_period', 20)->nullable();
            $table->text('compensation_text')->nullable();
            $table->json('benefits')->nullable();
            $table->date('publication_date')->nullable();
            $table->date('application_deadline')->nullable();
            $table->date('expected_start_date')->nullable();
            $table->string('employment_duration', 100)->nullable();
            $table->json('additional_requirements')->nullable();
            $table->char('source_hash', 64);
            $table->dateTime('saved_at');
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'ingestion_id']);
            $table->index(['candidate_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_opportunities');
    }
};
