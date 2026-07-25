<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_skills', function (Blueprint $table) {
            $table->string('custom_skill_name', 150)->nullable()->after('skill_id');
        });

        DB::table('candidate_skills')
            ->whereNull('skill_id')
            ->whereNotNull('evidence')
            ->orderBy('id')
            ->each(function (stdClass $row) {
                $evidence = json_decode($row->evidence, true);
                if (is_array($evidence) && count($evidence) > 0) {
                    $name = $evidence[0]['value'] ?? null;
                    if ($name !== null) {
                        DB::table('candidate_skills')
                            ->where('id', $row->id)
                            ->update(['custom_skill_name' => $name]);
                    }
                }
            });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE candidate_skills ADD UNIQUE INDEX candidate_skills_custom_name_unique (candidate_profile_id, custom_skill_name(100))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('candidate_skills', function (Blueprint $table) {
                $table->dropIndex('candidate_skills_custom_name_unique');
            });
        }

        Schema::table('candidate_skills', function (Blueprint $table) {
            $table->dropColumn('custom_skill_name');
        });
    }
};
