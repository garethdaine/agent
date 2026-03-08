<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentRunEvent;
use App\Models\AgentSystemState;
use App\Models\SchedulerHeartbeat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/agent-api');
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

        config()->set('cache.stores.redis', ['driver' => 'array']);
    }

    public function test_job_lifecycle_endpoints_work_for_owner(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/job.md';
        file_put_contents($taskFile, "# Job\n");

        $create = $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Workflow Job',
            'description' => 'test',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
            'env_json' => ['MODE' => 'test'],
        ]);

        $create->assertCreated();
        $jobId = $create->json('data.id');

        $this->postJson('/agent/api/v1/jobs/'.$jobId.'/run-now')
            ->assertStatus(202)
            ->assertJsonPath('data.idempotent_replay', false);

        Queue::assertPushed(ExecuteAgentRunJob::class);

        $this->postJson('/agent/api/v1/jobs/'.$jobId.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false);

        $run = AgentJobRun::query()->where('agent_job_id', $jobId)->latest('id')->firstOrFail();

        $this->postJson('/agent/api/v1/runs/'.$run->id.'/stop')
            ->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        $this->deleteJson('/agent/api/v1/jobs/'.$jobId)
            ->assertOk();

        $this->postJson('/agent/api/v1/jobs/'.$jobId.'/restore')
            ->assertOk()
            ->assertJsonPath('data.deleted_at', null);

        $this->getJson('/agent/api/v1/jobs/'.$jobId.'/runs')
            ->assertOk();

        $this->getJson('/agent/api/v1/runs/'.$run->id)
            ->assertOk();

        $this->getJson('/agent/api/v1/runs/'.$run->id.'/events')
            ->assertOk();
    }

    public function test_run_now_returns_conflict_when_overlap_active(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Conflict Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $this->sandboxBase.'/tasks/conflict.md',
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        file_put_contents($job->task_markdown_path, "# conflict\n");

        AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_RUNNING,
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

        $this->postJson('/agent/api/v1/jobs/'.$job->id.'/run-now')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RUN_OVERLAP_ACTIVE');
    }

    public function test_run_now_returns_conflict_when_job_is_rate_limited(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Queue::fake();

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limited Run Now',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $this->sandboxBase.'/tasks/rate-limited-run-now.md',
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        file_put_contents($job->task_markdown_path, "# rate limited\n");

        AgentSystemState::query()->updateOrCreate(
            ['key' => sprintf('job_rate_limit_hold_until:%d', $job->id)],
            ['value' => now('UTC')->addMinutes(10)->toIso8601String(), 'updated_at' => now('UTC')]
        );

        $this->postJson('/agent/api/v1/jobs/'.$job->id.'/run-now')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'JOB_RATE_LIMITED');
    }

    public function test_run_now_can_ignore_rate_limit_hold_when_requested(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Queue::fake();

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Ignore Rate Limit Hold',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $this->sandboxBase.'/tasks/ignore-rate-limit.md',
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        file_put_contents($job->task_markdown_path, "# ignore hold\n");

        AgentSystemState::query()->updateOrCreate(
            ['key' => sprintf('job_rate_limit_hold_until:%d', $job->id)],
            ['value' => now('UTC')->addMinutes(10)->toIso8601String(), 'updated_at' => now('UTC')]
        );

        $this->postJson('/agent/api/v1/jobs/'.$job->id.'/run-now', [
            'ignore_rate_limit_hold' => true,
        ])->assertStatus(202)
            ->assertJsonPath('data.idempotent_replay', false);

        Queue::assertPushed(ExecuteAgentRunJob::class);
    }

    public function test_stop_with_missing_pid_is_terminal_and_idempotent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Stop Missing PID',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $this->sandboxBase.'/tasks/stop-missing-pid.md',
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        file_put_contents($job->task_markdown_path, "# stop\n");

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_RUNNING,
            'pid' => null,
            'started_at' => now('UTC')->subSeconds(5),
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

        $this->postJson('/agent/api/v1/runs/'.$run->id.'/stop')
            ->assertStatus(202)
            ->assertJsonPath('data.status', AgentJobRun::STATUS_KILLED);

        $run->refresh();
        $this->assertSame(AgentJobRun::STATUS_KILLED, $run->status);
        $this->assertSame('pid_missing', $run->metadata_json['termination_mode'] ?? null);

        $this->postJson('/agent/api/v1/runs/'.$run->id.'/stop')
            ->assertOk()
            ->assertJsonPath('data.already_terminal', true);
    }

    public function test_events_return_rfc3339_millisecond_timestamps(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Event Timestamps',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $this->sandboxBase.'/tasks/event-ts.md',
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        file_put_contents($job->task_markdown_path, "# event\n");

        $run = AgentJobRun::query()->create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
            'status' => AgentJobRun::STATUS_SUCCEEDED,
            'started_at' => now('UTC')->subSecond(),
            'finished_at' => now('UTC'),
            'duration_ms' => 1000,
            'stdout_bytes_pre' => 0,
            'stdout_bytes_post' => 0,
            'stderr_bytes_pre' => 0,
            'stderr_bytes_post' => 0,
            'metadata_json' => [
                'output_truncated' => false,
                'redaction_count' => 0,
            ],
        ]);

        AgentRunEvent::query()->create([
            'agent_job_run_id' => $run->id,
            'event_type' => 'lifecycle',
            'sequence' => 1,
            'payload' => json_encode(['type' => 'state_transition']) ?: '{}',
            'event_ts' => now('UTC'),
        ]);

        $response = $this->getJson('/agent/api/v1/runs/'.$run->id.'/events')
            ->assertOk();

        $eventTs = (string) $response->json('data.0.event_ts');
        $createdAt = (string) $response->json('data.0.created_at');

        $this->assertMatchesRegularExpression('/\\.\\d{3}Z$/', $eventTs);
        $this->assertMatchesRegularExpression('/\\.\\d{3}Z$/', $createdAt);
    }

    public function test_non_versioned_agent_api_routes_return_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/agent/api/jobs')->assertNotFound();
        $this->postJson('/agent/api/jobs', [])->assertNotFound();
    }

    public function test_dashboard_metrics_window_formulas_and_global_backlog_are_correct(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $now = CarbonImmutable::parse('2026-02-12T12:00:00Z');
        CarbonImmutable::setTestNow($now);
        try {
            SchedulerHeartbeat::query()->create([
                'source' => 'scheduler_dispatch',
                'last_seen_at' => $now->subSeconds(30),
                'meta_json' => ['tick' => 'ok'],
            ]);

            $taskFile = $this->sandboxBase.'/tasks/dashboard-metrics.md';
            file_put_contents($taskFile, "# dashboard\n");

            $job = AgentJob::query()->create([
                'user_id' => $user->id,
                'name' => 'Dashboard Metrics',
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

            $makeRun = function (string $status, CarbonImmutable $createdAt, int $durationMs = 0) use ($job, $user): void {
                AgentJobRun::query()->create([
                    'agent_job_id' => $job->id,
                    'user_id' => $user->id,
                    'initiated_by_user_id' => $user->id,
                    'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
                    'status' => $status,
                    'duration_ms' => $durationMs,
                    'stdout_bytes_pre' => 0,
                    'stdout_bytes_post' => 0,
                    'stderr_bytes_pre' => 0,
                    'stderr_bytes_post' => 0,
                    'metadata_json' => ['output_truncated' => false, 'redaction_count' => 0],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            };

            $makeRun(AgentJobRun::STATUS_SUCCEEDED, $now->subHours(2), 1000);
            $makeRun(AgentJobRun::STATUS_FAILED, $now->subHours(3), 3000);
            $makeRun(AgentJobRun::STATUS_SKIPPED, $now->subHours(4), 9000);
            $makeRun(AgentJobRun::STATUS_SUCCEEDED, $now->subDays(3), 5000);
            $makeRun(AgentJobRun::STATUS_QUEUED, $now->subMinutes(10), 0);
            $makeRun(AgentJobRun::STATUS_QUEUED, $now->subMinute(), 0);
            $makeRun(AgentJobRun::STATUS_TIMED_OUT, $now->subDays(8), 7000);

            $this->getJson('/agent/api/v1/dashboard/metrics?window=24h')
                ->assertOk()
                ->assertJsonPath('data.window.key', '24h')
                ->assertJsonPath('data.metrics.runs_today', 5)
                ->assertJsonPath('data.metrics.success_rate_percent', 50)
                ->assertJsonPath('data.metrics.average_duration_ms', 2000)
                ->assertJsonPath('data.metrics.backlog_count', 2)
                ->assertJsonPath('data.metrics.oldest_queued_age_seconds', 600)
                ->assertJsonPath('data.metrics.window_terminal_total', 2)
                ->assertJsonPath('data.metrics.window_succeeded_total', 1)
                ->assertJsonPath('data.scheduler.status', 'healthy');

            $this->getJson('/agent/api/v1/dashboard/metrics?window=7d')
                ->assertOk()
                ->assertJsonPath('data.window.key', '7d')
                ->assertJsonPath('data.metrics.success_rate_percent', 66.7)
                ->assertJsonPath('data.metrics.average_duration_ms', 3000)
                ->assertJsonPath('data.metrics.window_terminal_total', 3)
                ->assertJsonPath('data.metrics.window_succeeded_total', 2);

            $this->getJson('/agent/api/v1/dashboard/metrics?window=bad')
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_job_show_can_include_task_markdown_content(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/show-task.md';
        file_put_contents($taskFile, "# Show Task\n\nInline content check.\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Show Job',
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

        $this->getJson('/agent/api/v1/jobs/'.$job->id.'?include_task_content=1')
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.task_markdown_content', "# Show Task\n\nInline content check.\n");
    }

    public function test_jobs_index_can_filter_user_jobs_and_build_jobs_separately(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/filter-source.md';
        file_put_contents($taskFile, "# Source filter\n");

        $buildJob = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Interrogation Build S12 T01',
            'description' => 'build generated',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => false,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
            'env_json' => [
                'AGENT_JOB_SOURCE' => 'interrogation_build',
            ],
        ]);

        $userJob = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Normal User Job',
            'description' => 'user created',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'command_template' => config('agent.default_templates.codex'),
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
            'env_json' => [
                'MODE' => 'user',
            ],
        ]);

        $this->getJson('/agent/api/v1/jobs?source=build')
            ->assertOk()
            ->assertJsonPath('data.0.id', $buildJob->id)
            ->assertJsonMissing(['id' => $userJob->id]);

        $this->getJson('/agent/api/v1/jobs?source=user')
            ->assertOk()
            ->assertJsonPath('data.0.id', $userJob->id)
            ->assertJsonMissing(['id' => $buildJob->id]);
    }
}
