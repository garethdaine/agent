<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delegation_tasks', function (Blueprint $table) {
            $table->foreignId('parent_delegation_task_id')
                ->nullable()
                ->default(null)
                ->after('delegation_graph_id')
                ->constrained('delegation_tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delegation_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_delegation_task_id');
        });
    }
};
