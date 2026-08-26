<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clarification_audit_events', function (Blueprint $table): void {
            $table->string('event', 60)->nullable()->after('field');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::table('clarification_audit_events', function (Blueprint $table): void {
            $table->dropIndex(['event']);
            $table->dropColumn('event');
        });
    }
};
