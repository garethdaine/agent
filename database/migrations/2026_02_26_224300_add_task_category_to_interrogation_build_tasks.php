<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interrogation_build_tasks', function (Blueprint $table): void {
            $table->string('task_category', 24)->default('custom')->after('sequence');
            $table->index(['interrogation_session_id', 'task_category'], 'interrogation_build_tasks_session_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('interrogation_build_tasks', function (Blueprint $table): void {
            $table->dropIndex('interrogation_build_tasks_session_category_idx');
            $table->dropColumn('task_category');
        });
    }
};
