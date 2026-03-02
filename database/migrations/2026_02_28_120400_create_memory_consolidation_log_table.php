<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates memory_consolidation_log table for consolidation tracking.
 *
 * Records consolidation operations with checkpoint support.
 * FK to users is nullable for system-wide consolidations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_consolidation_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('consolidation_type', 30);
            $table->integer('source_count');
            $table->text('result_summary')->nullable();
            $table->jsonb('checkpoint_json')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_consolidation_log');
    }
};
