<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_analysis_id')->constrained('match_analyses')->cascadeOnDelete();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('requirement_text', 500);
            $table->string('requirement_label', 255)->nullable();
            $table->string('importance', 20);
            $table->string('category', 30)->nullable();
            $table->string('match_state', 20);
            $table->decimal('factor', 4, 2);
            $table->foreignId('matched_candidate_skill_id')->nullable()->constrained('candidate_skills')->nullOnDelete();
            $table->json('evidence_refs')->nullable();
            $table->text('justification')->nullable();
            $table->string('confidence', 20)->nullable();
            $table->string('classifier_source', 255)->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();

            $table->index(['match_analysis_id', 'importance']);
            $table->index(['match_analysis_id', 'match_state']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE match_findings ADD CONSTRAINT match_findings_source_type_check CHECK (source_type IN ('job_requirement', 'job_opportunity_skill'))");
            DB::statement("ALTER TABLE match_findings ADD CONSTRAINT match_findings_importance_check CHECK (importance IN ('required', 'preferred'))");
            DB::statement("ALTER TABLE match_findings ADD CONSTRAINT match_findings_match_state_check CHECK (match_state IN ('matched', 'partial', 'gap', 'unknown'))");
            DB::statement('ALTER TABLE match_findings ADD CONSTRAINT match_findings_factor_check CHECK (factor IN (0, 0.2, 0.5, 1))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('match_findings');
    }
};
