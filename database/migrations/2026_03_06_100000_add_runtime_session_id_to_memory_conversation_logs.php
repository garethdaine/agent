<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memory_conversation_logs', function (Blueprint $table): void {
            $table->uuid('runtime_session_id')->nullable()->after('job_id');
            $table->foreign('runtime_session_id')->references('id')->on('runtime_sessions')->cascadeOnDelete();
        });

        Schema::table('memory_conversation_logs', function (Blueprint $table): void {
            $table->dropForeign(['run_id']);
            $table->dropForeign(['job_id']);
        });

        DB::statement('ALTER TABLE memory_conversation_logs ALTER COLUMN run_id DROP NOT NULL');
        DB::statement('ALTER TABLE memory_conversation_logs ALTER COLUMN job_id DROP NOT NULL');

        Schema::table('memory_conversation_logs', function (Blueprint $table): void {
            $table->foreign('run_id')->references('id')->on('agent_job_runs')->cascadeOnDelete();
            $table->foreign('job_id')->references('id')->on('agent_jobs')->cascadeOnDelete();
        });

        DB::statement(
            'ALTER TABLE memory_conversation_logs ADD CONSTRAINT memory_conversation_logs_source_check '
            .'CHECK ( (run_id IS NOT NULL AND runtime_session_id IS NULL) OR (run_id IS NULL AND runtime_session_id IS NOT NULL) )'
        );

        Schema::table('memory_conversation_logs', function (Blueprint $table): void {
            $table->index(['user_id', 'runtime_session_id', 'sequence'], 'memory_conversation_logs_user_runtime_seq_idx');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE memory_conversation_logs DROP CONSTRAINT IF EXISTS memory_conversation_logs_source_check');

        Schema::table('memory_conversation_logs', function (Blueprint $table): void {
            $table->dropIndex('memory_conversation_logs_user_runtime_seq_idx');
            $table->dropForeign(['runtime_session_id']);
        });

        DB::table('memory_conversation_logs')->whereNotNull('runtime_session_id')->delete();

        DB::statement('ALTER TABLE memory_conversation_logs ALTER COLUMN run_id SET NOT NULL');
        DB::statement('ALTER TABLE memory_conversation_logs ALTER COLUMN job_id SET NOT NULL');
    }
};
