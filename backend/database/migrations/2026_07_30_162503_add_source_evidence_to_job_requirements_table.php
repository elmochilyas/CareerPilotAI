<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_requirements', function (Blueprint $table) {
            $table->text('source_evidence')
                ->nullable()
                ->after('language_proficiency');
        });
    }

    public function down(): void
    {
        Schema::table('job_requirements', function (Blueprint $table) {
            $table->dropColumn('source_evidence');
        });
    }
};
