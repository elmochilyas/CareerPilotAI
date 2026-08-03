<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_opportunity_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_label', 255);
            $table->string('classification', 20);
            $table->string('proficiency', 30)->nullable();
            $table->decimal('years_experience', 4, 1)->nullable();
            $table->text('source_evidence')->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();

            $table->index(['job_opportunity_id', 'classification']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_opportunity_skills');
    }
};
