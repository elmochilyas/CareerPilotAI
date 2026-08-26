<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the global unique on job_opportunity_id which blocks versioning
        // and replace with a composite unique that allows multiple versions per opportunity
        // and an index for fast lookup. Only if the unique exists.

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Find and drop the unique index on job_opportunity_id if exists
            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    // The original migration used foreignIdFor(...)->unique() which creates resumes_job_opportunity_id_unique
                    $table->dropUnique('resumes_job_opportunity_id_unique');
                });
            } catch (Throwable $e) {
                // Index may not exist (e.g., already dropped) - ignore
            }

            Schema::table('resumes', function (Blueprint $table): void {
                // Keep an index for fast lookup by opportunity
                $table->index('job_opportunity_id', 'resumes_job_opportunity_id_index');
                // Composite index for versioning lookups (not unique, to allow multiple drafts if needed, but we enforce via app lock)
                $table->index(['candidate_profile_id', 'job_opportunity_id'], 'resumes_candidate_opportunity_index');
            });
        } else {
            // For sqlite (testing), drop the global unique so versioning can be tested.
            // SQLite stores the unique as an index; dropping it recreates the table.
            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    $table->dropUnique('resumes_job_opportunity_id_unique');
                });
            } catch (Throwable $e) {
                // Fallback: raw index name may differ on sqlite – try without name
                try {
                    Schema::table('resumes', function (Blueprint $table): void {
                        $table->dropUnique(['job_opportunity_id']);
                    });
                } catch (Throwable $e2) {
                    // Unique may already be absent – ignore
                }
            }
            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    $table->index('job_opportunity_id');
                    $table->index(['candidate_profile_id', 'job_opportunity_id']);
                });
            } catch (Throwable $e) {
                // ignore if already exists
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    $table->dropIndex('resumes_job_opportunity_id_index');
                    $table->dropIndex('resumes_candidate_opportunity_index');
                });
            } catch (Throwable $e) {
                // ignore
            }

            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    $table->unique('job_opportunity_id', 'resumes_job_opportunity_id_unique');
                });
            } catch (Throwable $e) {
                // ignore
            }
        } else {
            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    $table->dropIndex('resumes_job_opportunity_id_index');
                    $table->dropIndex('resumes_candidate_opportunity_index');
                });
            } catch (Throwable $e) {
            }
            // Best-effort re-add unique on sqlite
            try {
                Schema::table('resumes', function (Blueprint $table): void {
                    $table->unique('job_opportunity_id', 'resumes_job_opportunity_id_unique');
                });
            } catch (Throwable $e) {
            }
        }
    }
};
