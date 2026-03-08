<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentApiContractCoverageTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/agent-api-contract');
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

    public function test_api_returns_401_when_request_is_not_authenticated(): void
    {
        $this->getJson('/agent/api/v1/jobs')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_required_endpoint_status_contracts_are_covered(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/contracts.md';
        file_put_contents($taskFile, "# Contracts\n");

        $this->getJson('/agent/api/v1/jobs')->assertStatus(200);

        $createResponse = $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Contract Job',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ])->assertStatus(201);

        $jobId = (int) $createResponse->json('data.id');

        $this->postJson('/agent/api/v1/jobs/'.$jobId.'/run-now')
            ->assertStatus(202);

        Cache::store('redis')->forget('agent:run-now:'.hash('sha256', sprintf('run-now|%d|%d', $user->id, $jobId)));

        $this->getJson('/agent/api/v1/jobs/999999')
            ->assertStatus(404);

        $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Bad Payload',
            'cron_expression' => 'not-a-cron',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 120,
            'cooldown_seconds' => 0,
            'runner_type' => 'codex',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ])->assertStatus(422);

        AgentJobRun::query()->create([
            'agent_job_id' => $jobId,
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

        $this->postJson('/agent/api/v1/jobs/'.$jobId.'/run-now')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'RUN_OVERLAP_ACTIVE');
    }

    public function test_mutation_rate_limit_returns_429(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/rate-limit.md';
        file_put_contents($taskFile, "# Rate Limit\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Rate Limit Job',
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

        $rateLimited = false;

        for ($i = 0; $i < 40; $i++) {
            $response = $this->postJson('/agent/api/v1/jobs/'.$job->id.'/toggle');
            if ($response->status() === 429) {
                $response->assertJsonPath('error.code', 'RATE_LIMITED');
                $rateLimited = true;
                break;
            }
        }

        $this->assertTrue($rateLimited, 'Expected at least one 429 RATE_LIMITED response.');
    }

    public function test_run_now_returns_503_when_cache_backend_is_unavailable(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/queue-unavailable.md';
        file_put_contents($taskFile, "# Queue Unavailable\n");

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Queue Unavailable Job',
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

        Cache::shouldReceive('store')
            ->once()
            ->with('redis')
            ->andThrow(new \RuntimeException('cache unavailable'));

        $this->postJson('/agent/api/v1/jobs/'.$job->id.'/run-now')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'QUEUE_UNAVAILABLE');
    }
}
