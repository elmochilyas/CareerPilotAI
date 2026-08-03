<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_opportunity_ingestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('source_description');
            $table->string('source_url', 500)->nullable();
            $table->char('content_hash', 64);
            $table->string('personal_label', 255)->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('failure_reason')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->dateTime('last_retry_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['user_id', 'content_hash']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_opportunity_ingestions');
    }
};
