<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates memory_core_blocks table for Core Memory (Layer 1).
 *
 * Stores identity and operational blocks with version tracking.
 * FK to users (required) and agent_jobs (optional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_core_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('agent_jobs')->cascadeOnDelete();
            $table->string('block_type', 20); // 'identity' or 'operational'
            $table->string('block_key', 100);
            $table->text('content_text')->nullable();
            $table->jsonb('content_json')->nullable();
            $table->string('classification', 20)->default('internal');
            $table->integer('version')->default(1);
            $table->timestamps();

            // Unique index for job-specific blocks (non-null job_id)
            $table->unique(['user_id', 'block_key', 'job_id'], 'memory_core_blocks_user_key_job_unique');
            $table->index(['user_id', 'classification'], 'memory_core_blocks_user_classification_idx');
        });

        // Partial unique index for global blocks (null job_id)
        // PostgreSQL treats NULLs as distinct, so we need a separate partial index
        DB::statement('CREATE UNIQUE INDEX memory_core_blocks_user_key_global_unique ON memory_core_blocks (user_id, block_key) WHERE job_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_core_blocks');
    }
};
