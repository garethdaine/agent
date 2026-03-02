<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates memory_provider_usage table for API usage tracking.
 *
 * Tracks token usage and cost estimates per provider operation.
 * FK to users (required) and agent_job_runs (nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_provider_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('agent_job_runs')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('model', 100);
            $table->string('operation', 30);
            $table->integer('input_tokens');
            $table->integer('output_tokens')->nullable();
            $table->float('cost_estimate_usd')->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at'], 'memory_provider_usage_user_created_idx');
            $table->index(['provider', 'created_at'], 'memory_provider_usage_provider_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_provider_usage');
    }
};
