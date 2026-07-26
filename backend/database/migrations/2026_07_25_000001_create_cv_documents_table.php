<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_path', 500);
            $table->string('stored_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['user_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_documents');
    }
};
