<?php

namespace Tests\Feature;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentAuditLog;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentSystemState;
use App\Models\SchedulerHeartbeat;
use App\Support\Agent\DispatchDueService;
use App\Models\User;
use App\Support\Agent\ReconcileActiveRunsService;
use Carbon\CarbonImmutable;
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

    public function test_reconcile_uses_launch_fingerprint_when_job_changes(): void
    {
        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/fingerprint.md';
        file_put_contents($taskFile, "# Fingerprint\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Fingerprint Stable',
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
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_RUNNING,
            'pid' => getmypid(),
            'started_at' => now('UTC')->subSeconds(30),
            'resolved_executable_path' => 'php',
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
                'launch_fingerprint' => [
                    'executable' => '/does/not/match/live/cmdline',
                    'executable_token' => 'php',
                    'task_markdown_path' => '',
                ],
            ],
        ]);

        $job->task_markdown_path = $this->sandboxBase.'/tasks/changed-after-launch.md';
        $job->save();

        app(ReconcileActiveRunsService::class)->reconcile('boot');

        $run->refresh();
        $this->assertSame(AgentJobRun::STATUS_RUNNING, $run->status);
    }

    public function test_dst_spring_forward_nonexistent_local_time_is_not_dispatched(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/dst-spring.md';
        file_put_contents($taskFile, "# DST Spring\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'DST Spring Forward',
            'description' => null,
            'cron_expression' => '30 2 * * *',
            'timezone' => 'America/New_York',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $service = app(DispatchDueService::class);
        $service->dispatch(CarbonImmutable::parse('2026-03-08T06:59:00Z'));
        $service->dispatch(CarbonImmutable::parse('2026-03-08T07:30:00Z'));

        $this->assertSame(0, AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('trigger_type', AgentJobRun::TRIGGER_SCHEDULE)
            ->count());
    }

    public function test_dst_fall_back_repeated_hour_dispatches_twice_across_two_ticks(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/dst-fall.md';
        file_put_contents($taskFile, "# DST Fall\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'DST Fall Back',
            'description' => null,
            'cron_expression' => '30 1 * * *',
            'timezone' => 'America/New_York',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $service = app(DispatchDueService::class);
        $firstDue = CarbonImmutable::parse('2026-11-01T05:30:00Z');
        $secondDue = CarbonImmutable::parse('2026-11-01T06:30:00Z');

        $service->dispatch($firstDue);

        $firstRun = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('trigger_type', AgentJobRun::TRIGGER_SCHEDULE)
            ->orderBy('id')
            ->firstOrFail();
        $firstRun->status = AgentJobRun::STATUS_SUCCEEDED;
        $firstRun->finished_at = $firstDue->addMinute();
        $firstRun->duration_ms = 1000;
        $firstRun->save();

        $service->dispatch($secondDue);

        $dueWindows = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('trigger_type', AgentJobRun::TRIGGER_SCHEDULE)
            ->orderBy('due_window_utc_minute')
            ->pluck('due_window_utc_minute')
            ->map(fn ($value): string => CarbonImmutable::parse($value, 'UTC')->toIso8601String())
            ->all();

        $this->assertSame([
            $firstDue->toIso8601String(),
            $secondDue->toIso8601String(),
        ], $dueWindows);
    }

    public function test_overlap_precedence_wins_over_cooldown_when_both_apply(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/overlap-cooldown.md';
        file_put_contents($taskFile, "# Overlap Cooldown\n");

        $now = CarbonImmutable::now('UTC')->startOfMinute();
        $cron = sprintf('%d %d %d %d %d', $now->minute, $now->hour, $now->day, $now->month, $now->dayOfWeek);

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Overlap Before Cooldown',
            'description' => null,
            'cron_expression' => $cron,
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 3600,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => null,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_QUEUED,
            'duration_ms' => 0,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => ['output_truncated' => false, 'redaction_count' => 0],
        ]);

        AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => null,
            'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'started_at' => $now->subMinutes(3),
            'finished_at' => $now->subMinutes(1),
            'duration_ms' => 2000,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => ['output_truncated' => false, 'redaction_count' => 0],
        ]);

        app(DispatchDueService::class)->dispatch($now);

        $skipRun = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('trigger_type', AgentJobRun::TRIGGER_SCHEDULE)
            ->where('status', AgentJobRun::STATUS_SKIPPED)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('overlap', $skipRun->metadata_json['skip_reason'] ?? null);
    }

    public function test_delayed_scheduler_backfill_is_bounded_to_lookback_and_per_job_cap(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/backfill.md';
        file_put_contents($taskFile, "# Backfill\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Backfill Bounds',
            'description' => null,
            'cron_expression' => '* * * * *',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $now = CarbonImmutable::now('UTC')->startOfMinute();
        AgentSystemState::query()->updateOrCreate(
            ['key' => 'dispatch_last_minute_utc'],
            ['value' => $now->subHours(2)->toIso8601String(), 'updated_at' => $now]
        );

        app(DispatchDueService::class)->dispatch($now);

        $runs = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('trigger_type', AgentJobRun::TRIGGER_SCHEDULE)
            ->orderBy('due_window_utc_minute')
            ->get();

        $this->assertSame(5, $runs->count());
        $this->assertTrue(
            CarbonImmutable::parse((string) $runs->first()?->due_window_utc_minute, 'UTC')
                ->greaterThanOrEqualTo($now->subMinutes(15))
        );
    }

    public function test_duplicate_dispatch_is_idempotent_for_same_due_window(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $taskFile = $this->sandboxBase.'/tasks/idempotent.md';
        file_put_contents($taskFile, "# Idempotent\n");

        $now = CarbonImmutable::now('UTC')->startOfMinute();
        $cron = sprintf('%d %d %d %d %d', $now->minute, $now->hour, $now->day, $now->month, $now->dayOfWeek);

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Idempotent Dispatch',
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

        $service = app(DispatchDueService::class);
        $service->dispatch($now);
        $service->dispatch($now);

        $runs = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('trigger_type', AgentJobRun::TRIGGER_SCHEDULE)
            ->where('due_window_utc_minute', $now)
            ->get();

        $this->assertSame(1, $runs->count());
    }
}
