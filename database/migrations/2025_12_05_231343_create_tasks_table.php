<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('creator_id')->nullable(false);
            $table->uuid('assignee_id')->nullable(false);
            $table->text('title')->nullable(false);
            $table->text('description')->nullable();
            $table->timestampTz('due_date')->nullable(false);
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_completed')->default(false);
            $table->text('attachment_key')->nullable();
            $table->text('attachment_mime')->nullable();
            $table->timestampsTz();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('assignee_id', 'idx_tasks_assignee');
            $table->index('due_date', 'idx_tasks_due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
