<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates memory_formation_failures table for formation failure tracking.
 *
 * Stores failed formation attempts with serialized payload for backfill retry.
 * FK to users and agent_job_runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_formation_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('agent_job_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('failure_type', 30);
            $table->text('error_message');
            $table->integer('attempts')->default(1);
            $table->timestampTz('backfilled_at')->nullable();
            $table->jsonb('payload_json');
            $table->timestampTz('created_at');

            $table->index('backfilled_at', 'memory_formation_failures_backfilled_idx');
        });

        // Partial index for pending (not yet backfilled) failures
        DB::statement('CREATE INDEX memory_formation_failures_pending ON memory_formation_failures (id) WHERE backfilled_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_formation_failures');
    }
};
