<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('research_status', 20)->default('not_researched')->after('research');
            $table->unsignedSmallInteger('research_version')->default(1)->after('research_status');
            $table->string('research_failure_code', 50)->nullable()->after('researched_at');
            $table->string('name_normalized', 255)->nullable()->after('name');
            $table->string('website_canonical', 500)->nullable()->after('website');
            $table->index(['research_status']);
            $table->index(['name_normalized']);
            $table->index(['website_canonical']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['research_status']);
            $table->dropIndex(['name_normalized']);
            $table->dropIndex(['website_canonical']);
            $table->dropColumn([
                'research_status',
                'research_version',
                'research_failure_code',
                'name_normalized',
                'website_canonical',
            ]);
        });
    }
};
