<?php

namespace Tests\Feature;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentAuditLog;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentSystemState;
use App\Models\SchedulerHeartbeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgentDispatchDueCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/agent-dispatch');
        @mkdir($this->sandboxBase.'/bin', 0777, true);
        @mkdir($this->sandboxBase.'/tasks', 0777, true);
        @mkdir($this->sandboxBase.'/work', 0777, true);

        $claude = $this->sandboxBase.'/bin/claude';
        file_put_contents($claude, "#!/bin/sh\nexit 0\n");
        chmod($claude, 0755);

        config()->set('agent.allowed_task_markdown_bases', [$this->sandboxBase.'/tasks']);
        config()->set('agent.allowed_working_directory_bases', [$this->sandboxBase.'/work']);
        config()->set('agent.runner_executables', [
            'claude' => $claude,
            'codex' => $claude,
            'custom' => $claude,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $claude.' -p {{task_markdown_path}}',
            'codex' => $claude.' exec {{task_markdown_path}}',
        ]);
    }

    public function test_dispatch_due_creates_queued_run_and_heartbeat(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $taskFile = $this->sandboxBase.'/tasks/dispatch.md';
        file_put_contents($taskFile, "# Dispatch\n");

        $now = now('UTC');
        $cron = sprintf('%d %d %d %d %d', $now->minute, $now->hour, $now->day, $now->month, $now->dayOfWeek);

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Dispatch Me',
            'description' => 'test',
            'cron_expression' => $cron,
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $this->artisan('agent:dispatch-due')->assertSuccessful();

        $this->assertDatabaseHas('agent_job_runs', [
            'agent_job_id' => $job->id,
            'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
            'status' => AgentJobRun::STATUS_QUEUED,
        ]);

        $this->assertNotNull(SchedulerHeartbeat::query()->where('source', 'scheduler_dispatch')->first());
        $this->assertNotNull(AgentSystemState::query()->find('dispatch_last_minute_utc'));

        Queue::assertPushed(ExecuteAgentRunJob::class);
    }

    public function test_dispatch_records_deferred_capacity_audit_when_global_cap_is_reached(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/deferred.md';
        file_put_contents($taskFile, "# Deferred\n");

        $now = now('UTC');
        $cron = sprintf('%d %d %d %d %d', $now->minute, $now->hour, $now->day, $now->month, $now->dayOfWeek);

        for ($i = 1; $i <= 25; $i++) {
            AgentJob::query()->create([
                'user_id' => $user->id,
                'name' => 'Deferred '.$i,
                'description' => null,
                'cron_expression' => $cron,
                'timezone' => 'UTC',
                'is_enabled' => true,
                'max_runtime_seconds' => 120,
                'cooldown_seconds' => 0,
                'runner_type' => 'claude',
                'command_template' => config('agent.default_templates.claude'),
                'task_markdown_path' => $taskFile,
                'working_directory' => $this->sandboxBase.'/work',
            ]);
        }

        $this->artisan('agent:dispatch-due')->assertSuccessful();

        $this->assertSame(20, AgentJobRun::query()->count());
        $this->assertDatabaseHas('agent_audit_logs', [
            'action' => 'deferred_capacity',
            'target_type' => 'scheduler_dispatch',
            'target_id' => 'scheduler_dispatch',
        ]);

        $audit = AgentAuditLog::query()->where('action', 'deferred_capacity')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame(5, (int) ($audit->after_json['deferred_capacity_count'] ?? 0));
    }

    public function test_dispatch_reconciles_orphaned_active_runs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/reconcile.md';
        file_put_contents($taskFile, "# Reconcile\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Reconcile Me',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => null,
            'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
            'status' => AgentJobRun::STATUS_RUNNING,
            'pid' => 999_999,
            'started_at' => now('UTC')->subMinute(),
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        $this->artisan('agent:dispatch-due')->assertSuccessful();

        $run->refresh();
        $this->assertSame(AgentJobRun::STATUS_FAILED, $run->status);
        $this->assertSame('process_not_found', $run->metadata_json['reconcile_reason'] ?? null);
    }
}
