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
        Schema::create('delegation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegation_task_id')->constrained('delegation_tasks')->cascadeOnDelete();
            $table->foreignId('delegatee_profile_id')->constrained('delegatee_profiles');
            $table->foreignId('agent_job_run_id')->nullable()->constrained('agent_job_runs')->nullOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 24)->default('running');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error_code', 100)->nullable();
            $table->text('error_summary')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(
                ['delegation_task_id', 'attempt_number'],
                'delegation_attempts_task_attempt_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegation_attempts');
    }
};
