<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // skills: FULLTEXT index for fuzzy search in ResolveJobSkillsAction (MySQL only).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE skills ADD FULLTEXT INDEX ft_skills_name_search (normalized_name, name)');
        }

        // job_requirements: cover ORDER BY display_order used in RequirementCollector.
        Schema::table('job_requirements', function (Blueprint $table) {
            $table->index('display_order', 'job_requirements_display_order_index');
        });

        // cv_suggestions: composite index for dedup checks in AnalyzeCvTextAction.
        Schema::table('cv_suggestions', function (Blueprint $table) {
            $table->index(['cv_document_id', 'type', 'field_name'], 'cv_suggestions_dedup_index');
        });

        // match_findings: cover ORDER BY display_order for result rendering.
        Schema::table('match_findings', function (Blueprint $table) {
            $table->index('display_order', 'match_findings_display_order_index');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE skills DROP INDEX ft_skills_name_search');
        }

        Schema::table('job_requirements', function (Blueprint $table) {
            $table->dropIndex('job_requirements_display_order_index');
        });

        Schema::table('cv_suggestions', function (Blueprint $table) {
            $table->dropIndex('cv_suggestions_dedup_index');
        });

        Schema::table('match_findings', function (Blueprint $table) {
            $table->dropIndex('match_findings_display_order_index');
        });
    }
};
