<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS agent_jobs_user_name_active_unique ON agent_jobs (user_id, name) WHERE deleted_at IS NULL');
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE agent_jobs ADD CONSTRAINT agent_jobs_runner_type_check CHECK (runner_type IN ('claude','codex','custom'))");
            DB::statement("ALTER TABLE agent_job_runs ADD CONSTRAINT agent_job_runs_status_check CHECK (status IN ('queued','starting','running','stopping','succeeded','failed','killed','timed_out','skipped'))");
            DB::statement("ALTER TABLE agent_job_runs ADD CONSTRAINT agent_job_runs_trigger_check CHECK (trigger_type IN ('schedule','manual'))");
            DB::statement("ALTER TABLE agent_run_events ADD CONSTRAINT agent_run_events_type_check CHECK (event_type IN ('stdout','stderr','lifecycle'))");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS agent_jobs_user_name_active_unique');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE agent_jobs DROP CONSTRAINT IF EXISTS agent_jobs_runner_type_check');
            DB::statement('ALTER TABLE agent_job_runs DROP CONSTRAINT IF EXISTS agent_job_runs_status_check');
            DB::statement('ALTER TABLE agent_job_runs DROP CONSTRAINT IF EXISTS agent_job_runs_trigger_check');
            DB::statement('ALTER TABLE agent_run_events DROP CONSTRAINT IF EXISTS agent_run_events_type_check');
        }
    }
};
