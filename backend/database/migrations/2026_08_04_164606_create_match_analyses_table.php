<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->foreignId('job_opportunity_id')->constrained('job_opportunities')->cascadeOnDelete();
            $table->string('status', 30);
            $table->char('operation_key', 64);
            $table->unsignedSmallInteger('overall_score')->nullable();
            $table->unsignedSmallInteger('evidence_coverage_score')->nullable();
            $table->unsignedSmallInteger('required_count')->nullable();
            $table->unsignedSmallInteger('preferred_count')->nullable();
            $table->unsignedSmallInteger('matched_count')->nullable();
            $table->unsignedSmallInteger('partial_count')->nullable();
            $table->unsignedSmallInteger('gap_count')->nullable();
            $table->unsignedSmallInteger('unknown_count')->nullable();
            $table->char('profile_fingerprint', 64);
            $table->char('opportunity_fingerprint', 64);
            $table->dateTime('profile_updated_at')->nullable();
            $table->dateTime('opportunity_updated_at')->nullable();
            $table->string('algorithm_version', 30);
            $table->string('scoring_version', 30);
            $table->string('classifier_schema_version', 30);
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('processing_started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'operation_key']);
            $table->index(['job_opportunity_id', 'status', 'created_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE match_analyses ADD CONSTRAINT match_analyses_status_check CHECK (status IN ('queued', 'processing', 'completed', 'failed'))");
            DB::statement('ALTER TABLE match_analyses ADD CONSTRAINT match_analyses_overall_score_check CHECK (overall_score IS NULL OR (overall_score >= 0 AND overall_score <= 100))');
            DB::statement('ALTER TABLE match_analyses ADD CONSTRAINT match_analyses_evidence_coverage_score_check CHECK (evidence_coverage_score IS NULL OR (evidence_coverage_score >= 0 AND evidence_coverage_score <= 100))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_analyses');
    }
};
