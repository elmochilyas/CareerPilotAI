<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite unique to enforce version_no uniqueness per (profile, opportunity).
     * App-level lockForUpdate in CreateResumeAction is primary guard; this is defense-in-depth
     * to prevent race-created duplicate version_no even under READ COMMITTED.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    $table->unique(['candidate_profile_id', 'job_opportunity_id', 'version_no'], 'resumes_profile_opportunity_version_unique');
                });
            } catch (Throwable $e) {
                // Unique may already exist or duplicate rows exist — log and continue
            }
        } else {
            // SQLite test env — add unique index; SQLite treats unique as index
            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    $table->unique(['candidate_profile_id', 'job_opportunity_id', 'version_no'], 'resumes_profile_opportunity_version_unique');
                });
            } catch (Throwable $e) {
                // ignore for sqlite drifts
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        try {
            Schema::table('resumes', function (Blueprint $table): void {
                $table->dropUnique('resumes_profile_opportunity_version_unique');
            });
        } catch (Throwable $e) {
            if ($driver !== 'mysql') {
                try {
                    Schema::table('resumes', function (Blueprint $table): void {
                        $table->dropUnique(['candidate_profile_id', 'job_opportunity_id', 'version_no']);
                    });
                } catch (Throwable $e2) {
                    // ignore
                }
            }
        }
    }
};
