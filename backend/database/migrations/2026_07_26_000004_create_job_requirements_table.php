<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->text('content');
            $table->string('classification', 20)->nullable();
            $table->string('language', 100)->nullable();
            $table->string('language_proficiency', 30)->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();

            $table->index(['job_opportunity_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requirements');
    }
};
