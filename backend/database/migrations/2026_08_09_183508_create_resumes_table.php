<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(CandidateProfile::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(JobOpportunity::class)->nullable()->unique();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->string('title', 255);
            $table->string('template_key', 100)->nullable();
            $table->json('content');
            $table->string('status', 30)->default('draft');
            $table->string('generated_by', 30)->default('manual');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedSmallInteger('version_no')->default(1);
            $table->json('profile_snapshot')->nullable();
            $table->json('opportunity_snapshot')->nullable();
            $table->json('match_snapshot')->nullable();
            $table->json('ai_metadata')->nullable();
            $table->timestamps();

            $table->index('candidate_profile_id');
            $table->index(['candidate_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
