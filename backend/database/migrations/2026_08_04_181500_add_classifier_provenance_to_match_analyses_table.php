<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_analyses', function (Blueprint $table) {
            $table->string('classifier_provider', 50)->nullable();
            $table->string('classifier_model', 100)->nullable();
            $table->string('classifier_prompt_version', 30)->nullable();
            $table->unsignedSmallInteger('classifier_latency_ms')->nullable();
            $table->unsignedInteger('classifier_tokens_prompt')->nullable();
            $table->unsignedInteger('classifier_tokens_completion')->nullable();
            $table->string('classifier_response_id', 100)->nullable();
            $table->string('classifier_status', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('match_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'classifier_provider',
                'classifier_model',
                'classifier_prompt_version',
                'classifier_latency_ms',
                'classifier_tokens_prompt',
                'classifier_tokens_completion',
                'classifier_response_id',
                'classifier_status',
            ]);
        });
    }
};
