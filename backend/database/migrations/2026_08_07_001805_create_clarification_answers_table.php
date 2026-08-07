<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clarification_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('question_id')->unique()->constrained('clarification_questions')->cascadeOnDelete();
            $table->string('answer_type', 30);
            $table->text('value');
            $table->boolean('acknowledged_no_evidence')->default(false);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('proposal_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index('proposal_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE clarification_answers ADD CONSTRAINT clarification_answers_answer_type_check CHECK (answer_type IN ('yes', 'no', 'no_with_ack', 'text', 'select_option', 'number'))");
            DB::statement("ALTER TABLE clarification_answers ADD CONSTRAINT clarification_answers_status_check CHECK (status IN ('pending', 'accepted', 'rejected', 'skipped', 'expired'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clarification_answers');
    }
};
