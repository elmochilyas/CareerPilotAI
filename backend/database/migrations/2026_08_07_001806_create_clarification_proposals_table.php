<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarification_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->unique()->constrained('clarification_answers')->cascadeOnDelete();
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('field', 60);
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->string('status', 20)->default('proposed');
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE clarification_proposals ADD CONSTRAINT clarification_proposals_target_type_check CHECK (target_type IN ('candidate_skill', 'profile_item'))");
            DB::statement("ALTER TABLE clarification_proposals ADD CONSTRAINT clarification_proposals_status_check CHECK (status IN ('proposed', 'accepted', 'rejected', 'skipped'))");
        }

        Schema::table('clarification_answers', function (Blueprint $table) {
            $table->foreign('proposal_id')
                ->references('id')
                ->on('clarification_proposals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clarification_answers', function (Blueprint $table) {
            $table->dropForeign(['proposal_id']);
        });

        Schema::dropIfExists('clarification_proposals');
    }
};
