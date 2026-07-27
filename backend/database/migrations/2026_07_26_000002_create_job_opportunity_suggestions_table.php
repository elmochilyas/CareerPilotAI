<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_opportunity_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingestion_id')->constrained('job_opportunity_ingestions')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('group_key', 50)->nullable();
            $table->string('field', 100)->nullable();
            $table->json('extracted_value');
            $table->json('edited_value')->nullable();
            $table->string('review_decision', 30)->default('pending');
            $table->text('source_evidence')->nullable();
            $table->string('schema_version', 30);
            $table->dateTime('reviewed_at')->nullable();
            $table->string('resolution', 30)->nullable();
            $table->unsignedBigInteger('resolved_skill_id')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->foreign('resolved_skill_id')->references('id')->on('skills')->nullOnDelete();
            $table->index(['ingestion_id', 'type']);
            $table->index(['ingestion_id', 'group_key']);
            $table->index(['ingestion_id', 'review_decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_opportunity_suggestions');
    }
};
