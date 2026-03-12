<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add source_type and source_id columns to memory_conversation_logs.
 *
 * Extends the source identification to support interrogation sessions,
 * repo analysis sessions, and future source types beyond agent job runs
 * and runtime sessions.
 *
 * Updates the source_check constraint to allow three source modes:
 * 1. Agent job run: run_id IS NOT NULL
 * 2. Runtime session: runtime_session_id IS NOT NULL
 * 3. Generic source: source_type + source_id IS NOT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memory_conversation_logs', function (Blueprint $table): void {
            $table->string('source_type', 50)->nullable()->after('runtime_session_id');
            $table->string('source_id')->nullable()->after('source_type');
        });

        // Drop the existing XOR constraint
        DB::statement('ALTER TABLE memory_conversation_logs DROP CONSTRAINT IF EXISTS memory_conversation_logs_source_check');

        // Add relaxed constraint: exactly one source identification mode must be set
        DB::statement(
            'ALTER TABLE memory_conversation_logs ADD CONSTRAINT memory_conversation_logs_source_check '
            .'CHECK ( '
            .'(run_id IS NOT NULL AND runtime_session_id IS NULL AND source_type IS NULL) '
            .'OR (run_id IS NULL AND runtime_session_id IS NOT NULL AND source_type IS NULL) '
            .'OR (run_id IS NULL AND runtime_session_id IS NULL AND source_type IS NOT NULL AND source_id IS NOT NULL)'
            .' )'
        );

        Schema::table('memory_conversation_logs', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'source_type', 'source_id', 'sequence'],
                'memory_conversation_logs_source_type_idx'
            );
        });
    }

    public function down(): void
    {
        // Drop the relaxed constraint
        DB::statement('ALTER TABLE memory_conversation_logs DROP CONSTRAINT IF EXISTS memory_conversation_logs_source_check');

        // Restore the original XOR constraint
        DB::statement(
            'ALTER TABLE memory_conversation_logs ADD CONSTRAINT memory_conversation_logs_source_check '
            .'CHECK ( (run_id IS NOT NULL AND runtime_session_id IS NULL) OR (run_id IS NULL AND runtime_session_id IS NOT NULL) )'
        );

        // Remove records that used the generic source (they'd violate the restored constraint)
        DB::table('memory_conversation_logs')->whereNotNull('source_type')->delete();

        Schema::table('memory_conversation_logs', function (Blueprint $table): void {
            $table->dropIndex('memory_conversation_logs_source_type_idx');
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
