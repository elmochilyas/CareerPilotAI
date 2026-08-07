<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_skills', function (Blueprint $table) {
            $table->string('proficiency_level', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_skills', function (Blueprint $table) {
            $table->string('proficiency_level', 30)->nullable(false)->change();
        });
    }
};
