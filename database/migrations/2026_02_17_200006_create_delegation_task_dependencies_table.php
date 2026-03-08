<?php

declare(strict_types=1);

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
        Schema::create('delegation_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('delegation_tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('delegation_tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['task_id', 'depends_on_task_id'],
                'delegation_task_deps_task_depends_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegation_task_dependencies');
    }
};
