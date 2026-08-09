<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarification_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->nullable()->constrained('clarification_answers')->nullOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('clarification_proposals')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('match_analysis_id')->nullable()->constrained('match_analyses')->nullOnDelete();
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('field', 60)->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['answer_id', 'proposal_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('match_analysis_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE clarification_audit_events ADD CONSTRAINT clarification_audit_events_target_type_check CHECK (target_type IN ('candidate_skill', 'profile_item'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clarification_audit_events');
    }
};
