<?php

use App\Models\Resume;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tailoring_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Resume::class)->constrained()->cascadeOnDelete();
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->longText('original_text');
            $table->longText('proposed_text');
            $table->string('change_type', 30);
            $table->string('status', 30)->default('proposed');
            $table->longText('edited_text')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->json('ai_metadata')->nullable();
            $table->timestamps();

            $table->index('resume_id');
            $table->index(['resume_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tailoring_proposals');
    }
};
