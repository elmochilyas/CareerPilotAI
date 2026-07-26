<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_processing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_document_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('pipeline_version', 30);
            $table->char('idempotency_key', 64)->unique();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->string('ai_provider', 100)->nullable();
            $table->string('ai_model', 100)->nullable();
            $table->string('ai_prompt_version', 30)->nullable();
            $table->unsignedInteger('ai_latency_ms')->nullable();
            $table->unsignedInteger('ai_tokens_prompt')->nullable();
            $table->unsignedInteger('ai_tokens_completion')->nullable();
            $table->decimal('ai_cost_estimate', 10, 6)->nullable();
            $table->string('ai_response_id', 255)->nullable();
            $table->timestamps();

            $table->index(['cv_document_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_processing_runs');
    }
};
