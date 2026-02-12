<?php

namespace Tests\Feature;

use App\Models\AgentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentJobValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/agent-sandbox');
        @mkdir($this->sandboxBase.'/bin', 0777, true);
        @mkdir($this->sandboxBase.'/tasks', 0777, true);
        @mkdir($this->sandboxBase.'/work', 0777, true);

        $claude = $this->sandboxBase.'/bin/claude';
        $codex = $this->sandboxBase.'/bin/codex';
        $custom = $this->sandboxBase.'/bin/agent-runner';

        file_put_contents($claude, "#!/bin/sh\nexit 0\n");
        file_put_contents($codex, "#!/bin/sh\nexit 0\n");
        file_put_contents($custom, "#!/bin/sh\nexit 0\n");

        chmod($claude, 0755);
        chmod($codex, 0755);
        chmod($custom, 0755);

        config()->set('agent.allowed_task_markdown_bases', [$this->sandboxBase.'/tasks']);
        config()->set('agent.allowed_working_directory_bases', [$this->sandboxBase.'/work']);
        config()->set('agent.runner_executables', [
            'claude' => $claude,
            'codex' => $codex,
            'custom' => $custom,
        ]);
        config()->set('agent.default_templates', [
            'claude' => $claude.' -p {{task_markdown_path}}',
            'codex' => $codex.' exec {{task_markdown_path}}',
        ]);
    }

    public function test_user_can_create_job_with_valid_payload(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/test-task.md';
        file_put_contents($taskFile, "# Test\n");

        $payload = [
            'name' => 'Daily Agent Job',
            'description' => 'Run every minute',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
            'env_json' => [
                'AGENT_MODE' => 'test',
            ],
        ];

        $response = $this->postJson('/agent/api/v1/jobs', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Daily Agent Job');

        $this->assertDatabaseCount('agent_jobs', 1);
        $job = AgentJob::first();

        $this->assertSame($user->id, $job->user_id);
        $this->assertNotNull($job->last_validated_executable_path);
        $this->assertStringContainsString('claude', $job->command_template);
    }

    public function test_user_can_create_job_with_inline_markdown_content(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Inline Markdown Job',
            'description' => 'Inline prompt',
            'cron_expression' => '0 9 * * 1-5',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'task_markdown_content' => "# Inline Task\n\nRun a quick check.\n",
            'working_directory' => $this->sandboxBase.'/work',
            'env_json' => [
                'AGENT_MODE' => 'test',
            ],
        ];

        $response = $this->postJson('/agent/api/v1/jobs', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Inline Markdown Job');

        $job = AgentJob::query()->where('name', 'Inline Markdown Job')->firstOrFail();
        $this->assertStringContainsString($this->sandboxBase.'/tasks', (string) $job->task_markdown_path);
        $this->assertFileExists((string) $job->task_markdown_path);
        $this->assertStringContainsString('Inline Task', file_get_contents((string) $job->task_markdown_path));
    }

    public function test_invalid_cron_returns_validation_error_envelope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/test-task.md';
        file_put_contents($taskFile, "# Test\n");

        $payload = [
            'name' => 'Bad Cron',
            'cron_expression' => 'every day at noon',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ];

        $response = $this->postJson('/agent/api/v1/jobs', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonStructure([
            'error' => [
                'code',
                'message',
                'details' => [
                    'cron_expression',
                ],
            ],
        ]);
    }

    public function test_numeric_cron_with_wildcards_is_allowed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/wildcard-task.md';
        file_put_contents($taskFile, "# Test\n");

        $payload = [
            'name' => 'Wildcard Cron',
            'cron_expression' => '0 9 * * 1-5',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ];

        $this->postJson('/agent/api/v1/jobs', $payload)
            ->assertCreated()
            ->assertJsonPath('data.cron_expression', '0 9 * * 1-5');
    }

    public function test_env_json_forbidden_key_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/test-task.md';
        file_put_contents($taskFile, "# Test\n");

        $payload = [
            'name' => 'Forbidden Env',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
            'env_json' => [
                'API_TOKEN' => '123',
            ],
        ];

        $response = $this->postJson('/agent/api/v1/jobs', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonFragment(['This environment key is not allowed.']);
        $this->assertArrayHasKey('env_json.API_TOKEN', $response->json('error.details'));
    }

    public function test_custom_runner_requires_task_markdown_placeholder(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $taskFile = $this->sandboxBase.'/tasks/test-task.md';
        file_put_contents($taskFile, "# Test\n");

        $payload = [
            'name' => 'Custom Missing Placeholder',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'custom',
            'command_template' => $this->sandboxBase.'/bin/agent-runner --flag only',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ];

        $response = $this->postJson('/agent/api/v1/jobs', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath(
            'error.details.command_template.0',
            'Custom runner templates must include {{task_markdown_path}}.'
        );
    }
}
