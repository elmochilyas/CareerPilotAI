<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->string('organization')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();
            $table->index(['candidate_profile_id', 'type', 'display_order']);
        });

        DB::statement("ALTER TABLE profile_items ADD CONSTRAINT profile_items_type_check CHECK (type IN ('education', 'experience', 'project', 'certification'))");
        DB::statement('ALTER TABLE profile_items ADD CONSTRAINT profile_items_date_range_check CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_items');
    }
};
