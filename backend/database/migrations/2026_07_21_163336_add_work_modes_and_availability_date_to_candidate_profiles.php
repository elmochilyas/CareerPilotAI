<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->json('work_modes')->nullable()->after('work_mode');
            $table->date('availability_date')->nullable()->after('availability_status');
        });

        DB::statement('UPDATE candidate_profiles SET work_modes = CASE WHEN work_mode IS NOT NULL THEN JSON_ARRAY(work_mode) ELSE NULL END WHERE work_modes IS NULL');
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table): void {
            $table->dropColumn(['work_modes', 'availability_date']);
        });
    }
};
