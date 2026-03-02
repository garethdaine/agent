<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repo_analysis_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repo_analysis_session_id')->constrained('repo_analysis_sessions')->cascadeOnDelete();
            $table->string('task_key', 191);
            $table->string('task_type', 64);
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('phase')->nullable();
            $table->json('depends_on_json')->nullable();
            $table->json('artifact_ids_json')->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->string('output_hash', 64)->nullable();
            $table->string('analyzer_name', 128)->nullable();
            $table->string('analyzer_version', 64)->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(2);
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['repo_analysis_session_id', 'task_key'],
                'repo_analysis_tasks_session_task_key_unique'
            );
            $table->index(
                ['repo_analysis_session_id', 'status'],
                'repo_analysis_tasks_session_status_idx'
            );
            $table->index(['status', 'phase'], 'repo_analysis_tasks_status_phase_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_analysis_tasks');
    }
};
