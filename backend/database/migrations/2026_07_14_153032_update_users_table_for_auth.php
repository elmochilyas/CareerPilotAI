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
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
            $table->string('role', 30)->default('candidate')->after('email');
            $table->string('account_status', 30)->default('active')->after('role');
            $table->string('timezone', 64)->default('UTC')->after('account_status');
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index('account_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('full_name', 'name');
            $table->dropColumn(['role', 'account_status', 'timezone', 'deleted_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['account_status']);
        });
    }
};
