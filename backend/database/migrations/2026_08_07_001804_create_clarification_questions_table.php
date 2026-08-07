<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarification_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_analysis_id')->constrained('match_analyses')->cascadeOnDelete();
            $table->foreignId('match_finding_id')->nullable()->constrained('match_findings')->cascadeOnDelete();
            $table->unsignedSmallInteger('question_no');
            $table->string('question_type', 30);
            $table->string('prompt', 500);
            $table->string('detail', 1000)->nullable();
            $table->string('template_key', 100);
            $table->json('options_json')->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('ai_metadata')->nullable();
            $table->timestamps();

            $table->index(['match_analysis_id', 'status']);
            $table->index(['match_analysis_id', 'match_finding_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE clarification_questions ADD CONSTRAINT clarification_questions_question_type_check CHECK (question_type IN ('yes_no', 'yes_no_with_details', 'text', 'select', 'number'))");
            DB::statement("ALTER TABLE clarification_questions ADD CONSTRAINT clarification_questions_status_check CHECK (status IN ('pending', 'answered', 'skipped', 'expired'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clarification_questions');
    }
};
