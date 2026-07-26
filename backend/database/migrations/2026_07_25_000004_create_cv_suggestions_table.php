<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cv_processing_run_id')->constrained();
            $table->string('type', 50);
            $table->string('category', 50)->nullable();
            $table->string('field_name', 100)->nullable();
            $table->json('current_value')->nullable();
            $table->json('suggested_value');
            $table->unsignedInteger('source_page')->nullable();
            $table->text('source_text')->nullable();
            $table->string('extraction_method', 50);
            $table->string('schema_version', 30);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('review_status', 30)->default('pending');
            $table->json('reviewed_decision')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('cv_import_batches')->nullOnDelete();
            $table->string('import_status', 30)->nullable();
            $table->unsignedBigInteger('applied_profile_id')->nullable();
            $table->unsignedBigInteger('applied_skill_id')->nullable();
            $table->timestamps();

            $table->index(['cv_document_id', 'review_status']);
            $table->index(['type', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_suggestions');
    }
};
