<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interrogation_build_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interrogation_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->longText('instructions_markdown')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->foreignId('agent_job_run_id')->nullable()->constrained('agent_job_runs')->nullOnDelete();
            $table->text('last_error')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->index(['interrogation_session_id', 'status'], 'interrogation_build_tasks_session_status_idx');
            $table->unique(['interrogation_session_id', 'sequence'], 'interrogation_build_tasks_session_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interrogation_build_tasks');
    }
};
