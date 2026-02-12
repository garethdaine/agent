<?php

namespace Tests\Feature;

use App\Models\AgentAuditLog;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentMaintenanceCheckpoint;
use App\Models\AgentRunEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentMaintenancePruneTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/agent-prune');
        @mkdir($this->sandboxBase.'/bin', 0777, true);
        @mkdir($this->sandboxBase.'/tasks', 0777, true);
        @mkdir($this->sandboxBase.'/work', 0777, true);

        $exec = $this->sandboxBase.'/bin/runner';
        file_put_contents($exec, "#!/bin/sh\nexit 0\n");
        chmod($exec, 0755);

        config()->set('agent.allowed_task_markdown_bases', [$this->sandboxBase.'/tasks']);
        config()->set('agent.allowed_working_directory_bases', [$this->sandboxBase.'/work']);
        config()->set('agent.runner_executables', [
            'claude' => $exec,
            'codex' => $exec,
            'custom' => $exec,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $exec.' -p {{task_markdown_path}}',
            'codex' => $exec.' exec {{task_markdown_path}}',
        ]);
    }

    public function test_mutating_job_actions_write_audit_rows(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/audit.md';
        file_put_contents($taskFile, "# Audit\n");

        $create = $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Audited Job',
            'description' => 'mutate',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ])->assertCreated();

        $jobId = (int) $create->json('data.id');

        $this->postJson('/agent/api/v1/jobs/'.$jobId.'/toggle')->assertOk();
        $this->deleteJson('/agent/api/v1/jobs/'.$jobId)->assertOk();
        $this->postJson('/agent/api/v1/jobs/'.$jobId.'/restore')->assertOk();

        $this->assertDatabaseHas('agent_audit_logs', ['action' => 'job.create', 'user_id' => $user->id]);
        $this->assertDatabaseHas('agent_audit_logs', ['action' => 'job.toggle', 'user_id' => $user->id]);
        $this->assertDatabaseHas('agent_audit_logs', ['action' => 'job.delete', 'user_id' => $user->id]);
        $this->assertDatabaseHas('agent_audit_logs', ['action' => 'job.restore', 'user_id' => $user->id]);
    }

    public function test_prune_runs_respects_last_20_per_job_and_writes_checkpoint(): void
    {
        $user = User::factory()->create();
        $job = $this->createJob($user, 'Run Prune Job');

        for ($i = 1; $i <= 25; $i++) {
            AgentJobRun::query()->create([
                'agent_job_id' => $job->id,
                'user_id' => $user->id,
                'initiated_by_user_id' => null,
                'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
                'due_window_utc_minute' => now('UTC')->subDays(31)->subMinutes($i),
                'status' => AgentJobRun::STATUS_SUCCEEDED,
                'started_at' => now('UTC')->subDays(31)->subMinutes($i)->subSeconds(5),
                'finished_at' => now('UTC')->subDays(31)->subMinutes($i),
                'duration_ms' => 5000,
                'stdout_bytes_pre' => 0,
                'stdout_bytes_post' => 0,
                'stderr_bytes_pre' => 0,
                'stderr_bytes_post' => 0,
                'metadata_json' => ['output_truncated' => false, 'redaction_count' => 0],
                'created_at' => now('UTC')->subDays(31)->subMinutes($i),
                'updated_at' => now('UTC')->subDays(31)->subMinutes($i),
            ]);
        }

        $this->artisan('agent:prune --runs --dry-run --json')
            ->assertSuccessful()
            ->expectsOutputToContain('"dry_run": true');

        $this->assertSame(25, AgentJobRun::query()->count());

        $this->artisan('agent:prune --runs --chunk=7')
            ->assertSuccessful();

        $this->assertSame(20, AgentJobRun::query()->count());
        $this->assertDatabaseHas('agent_audit_logs', ['action' => 'maintenance.prune.runs', 'actor_type' => 'system']);

        $checkpoint = AgentMaintenanceCheckpoint::query()->where('domain', 'runs')->latest('id')->first();
        $this->assertNotNull($checkpoint);
        $this->assertSame('completed', $checkpoint->status);
        $this->assertSame(5, (int) $checkpoint->processed_rows);
    }

    public function test_prune_events_skips_active_runs_and_prune_jobs_cascades_children(): void
    {
        $user = User::factory()->create();

        $job = $this->createJob($user, 'Event Prune Job');
        $terminalRun = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => null,
            'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'started_at' => now('UTC')->subDays(8)->subMinute(),
            'finished_at' => now('UTC')->subDays(8),
            'duration_ms' => 1000,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => ['output_truncated' => false, 'redaction_count' => 0],
            'created_at' => now('UTC')->subDays(8),
            'updated_at' => now('UTC')->subDays(8),
        ]);

        $activeRun = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => null,
            'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
            'status' => AgentJobRun::STATUS_RUNNING,
            'started_at' => now('UTC')->subDays(8),
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => ['output_truncated' => false, 'redaction_count' => 0],
            'created_at' => now('UTC')->subDays(8),
            'updated_at' => now('UTC')->subDays(8),
        ]);

        $oldTerminalEvent = AgentRunEvent::query()->create([
            'agent_job_run_id' => $terminalRun->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => 'old terminal',
            'event_ts' => now('UTC')->subDays(8),
            'created_at' => now('UTC')->subDays(8),
            'updated_at' => now('UTC')->subDays(8),
        ]);

        $oldActiveEvent = AgentRunEvent::query()->create([
            'agent_job_run_id' => $activeRun->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => 'old active',
            'event_ts' => now('UTC')->subDays(8),
            'created_at' => now('UTC')->subDays(8),
            'updated_at' => now('UTC')->subDays(8),
        ]);

        $this->artisan('agent:prune --events')->assertSuccessful();

        $this->assertDatabaseMissing('agent_run_events', ['id' => $oldTerminalEvent->id]);
        $this->assertDatabaseHas('agent_run_events', ['id' => $oldActiveEvent->id]);

        $deletedJob = $this->createJob($user, 'Deleted Old Job');
        $deletedRun = AgentJobRun::query()->create([
            'agent_job_id' => $deletedJob->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => null,
            'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'started_at' => now('UTC')->subDays(40)->subMinute(),
            'finished_at' => now('UTC')->subDays(40),
            'duration_ms' => 1000,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => ['output_truncated' => false, 'redaction_count' => 0],
            'created_at' => now('UTC')->subDays(40),
            'updated_at' => now('UTC')->subDays(40),
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $deletedRun->id,
            'event_type' => 'stdout',
            'sequence' => 1,
            'payload' => 'deleted job event',
            'event_ts' => now('UTC')->subDays(40),
            'created_at' => now('UTC')->subDays(40),
            'updated_at' => now('UTC')->subDays(40),
        ]);

        $deletedJob->delete();
        DB::table('agent_jobs')->where('id', $deletedJob->id)->update(['deleted_at' => now('UTC')->subDays(31)]);

        $this->artisan('agent:prune --jobs')->assertSuccessful();

        $this->assertNull(AgentJob::query()->withTrashed()->find($deletedJob->id));
        $this->assertNull(AgentJobRun::query()->find($deletedRun->id));
    }

    public function test_prune_audit_removes_rows_older_than_ninety_days(): void
    {
        AgentAuditLog::query()->create([
            'user_id' => null,
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'old',
            'target_type' => 'maintenance',
            'target_id' => 'old',
            'created_at' => now('UTC')->subDays(100),
        ]);

        AgentAuditLog::query()->create([
            'user_id' => null,
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'new',
            'target_type' => 'maintenance',
            'target_id' => 'new',
            'created_at' => now('UTC')->subDays(10),
        ]);

        $this->artisan('agent:prune --audit')->assertSuccessful();

        $this->assertDatabaseMissing('agent_audit_logs', ['action' => 'old']);
        $this->assertDatabaseHas('agent_audit_logs', ['action' => 'new']);
    }

    private function createJob(User $user, string $name): AgentJob
    {
        $taskFile = $this->sandboxBase.'/tasks/'.strtolower(str_replace(' ', '-', $name)).'.md';
        file_put_contents($taskFile, "# {$name}\n");

        return AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);
    }
}
