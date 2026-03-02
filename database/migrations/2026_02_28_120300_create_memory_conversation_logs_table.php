<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates memory_conversation_logs table for Long-term Memory (Layer 3).
 *
 * Immutable conversation records with role and sequence tracking.
 * FK to users, agent_job_runs, and agent_jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_conversation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('agent_job_runs')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('agent_jobs')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->integer('sequence');
            $table->string('event_type', 20);
            $table->string('classification', 20)->default('internal');
            $table->timestampTz('created_at');

            $table->index(['user_id', 'run_id', 'sequence'], 'memory_conversation_logs_user_run_seq_idx');
            $table->index(['user_id', 'classification'], 'memory_conversation_logs_user_classification_idx');
        });

        // BM25 tsvector index for keyword search
        DB::statement("CREATE INDEX memory_conversation_logs_content_fts ON memory_conversation_logs USING GIN (to_tsvector('english', content))");
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_conversation_logs');
    }
};
