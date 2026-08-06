<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_scores', function (Blueprint $table) {
            $table->boolean('has_candidate_data')->default(true)->after('total_points');
        });
    }

    public function down(): void
    {
        Schema::table('match_scores', function (Blueprint $table) {
            $table->dropColumn('has_candidate_data');
        });
    }
};
