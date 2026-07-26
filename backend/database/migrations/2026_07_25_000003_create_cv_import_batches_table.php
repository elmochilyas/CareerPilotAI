<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->char('idempotency_key', 64)->unique();
            $table->dateTime('imported_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_import_batches');
    }
};
