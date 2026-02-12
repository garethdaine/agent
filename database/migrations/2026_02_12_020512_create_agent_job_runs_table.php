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
        Schema::create('agent_job_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_job_id')->constrained('agent_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger_type', 16);
            $table->timestampTz('due_window_utc_minute')->nullable();
            $table->string('status', 24);
            $table->unsignedBigInteger('pid')->nullable();
            $table->string('resolved_executable_path', 1024)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('signal', 32)->nullable();
            $table->unsignedBigInteger('duration_ms')->default(0);
            $table->unsignedBigInteger('stdout_bytes_pre')->default(0);
            $table->unsignedBigInteger('stdout_bytes_post')->default(0);
            $table->unsignedBigInteger('stderr_bytes_pre')->default(0);
            $table->unsignedBigInteger('stderr_bytes_post')->default(0);
            $table->text('error_summary')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['agent_job_id', 'status', 'created_at'], 'agent_job_runs_job_status_created_idx');
            $table->index(['user_id', 'created_at'], 'agent_job_runs_user_created_idx');
            $table->unique(
                ['agent_job_id', 'due_window_utc_minute', 'trigger_type'],
                'agent_job_runs_schedule_idempotency_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_job_runs');
    }
};
