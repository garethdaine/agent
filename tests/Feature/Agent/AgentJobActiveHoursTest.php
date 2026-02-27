<?php

namespace Tests\Feature\Agent;

use App\Models\AgentJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentJobActiveHoursTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->sandboxBase = storage_path('framework/testing/agent-active-hours');
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

    public function test_create_job_with_active_hours_config(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Test Job With Active Hours',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'runner_type' => 'claude',
            'task_markdown_content' => '# Test Task',
            'working_directory' => $this->sandboxBase.'/work',
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
                'days' => [1, 2, 3, 4, 5],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('agent_jobs', [
            'name' => 'Test Job With Active Hours',
        ]);

        $job = AgentJob::where('name', 'Test Job With Active Hours')->first();
        $this->assertEquals('09:00', $job->active_hours_config['start']);
        $this->assertEquals('17:00', $job->active_hours_config['end']);
        $this->assertEquals([1, 2, 3, 4, 5], $job->active_hours_config['days']);
    }

    public function test_create_job_without_active_hours(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Test Job Without Active Hours',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'runner_type' => 'claude',
            'task_markdown_content' => '# Test Task',
            'working_directory' => $this->sandboxBase.'/work',
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
        ]);

        $response->assertCreated();
        $job = AgentJob::where('name', 'Test Job Without Active Hours')->first();
        $this->assertNull($job->active_hours_config);
    }

    public function test_update_job_with_active_hours(): void
    {
        Sanctum::actingAs($this->user);

        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'working_directory' => $this->sandboxBase.'/work',
            'active_hours_config' => null,
        ]);

        $response = $this->putJson("/agent/api/v1/jobs/{$job->id}", [
            'name' => $job->name,
            'cron_expression' => $job->cron_expression,
            'timezone' => $job->timezone,
            'runner_type' => $job->runner_type,
            'task_markdown_content' => '# Test Task',
            'working_directory' => $this->sandboxBase.'/work',
            'max_runtime_seconds' => $job->max_runtime_seconds,
            'cooldown_seconds' => $job->cooldown_seconds,
            'active_hours_config' => [
                'start' => '10:00',
                'end' => '18:00',
                'days' => [1, 2, 3, 4, 5],
            ],
        ]);

        $response->assertOk();
        $job->refresh();
        $this->assertEquals('10:00', $job->active_hours_config['start']);
        $this->assertEquals('18:00', $job->active_hours_config['end']);
        $this->assertEquals([1, 2, 3, 4, 5], $job->active_hours_config['days']);
    }

    public function test_disable_active_hours_clears_config(): void
    {
        Sanctum::actingAs($this->user);

        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'working_directory' => $this->sandboxBase.'/work',
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
                'days' => [1, 2, 3, 4, 5],
            ],
        ]);

        $response = $this->putJson("/agent/api/v1/jobs/{$job->id}", [
            'name' => $job->name,
            'cron_expression' => $job->cron_expression,
            'timezone' => $job->timezone,
            'runner_type' => $job->runner_type,
            'task_markdown_content' => '# Test Task',
            'working_directory' => $this->sandboxBase.'/work',
            'max_runtime_seconds' => $job->max_runtime_seconds,
            'cooldown_seconds' => $job->cooldown_seconds,
            'disable_active_hours' => true,
        ]);

        $response->assertOk();
        $job->refresh();
        $this->assertNull($job->active_hours_config);
    }

    public function test_rejects_invalid_active_hours_schema(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Test Job Invalid Schema',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'runner_type' => 'claude',
            'task_markdown_content' => '# Test Task',
            'working_directory' => $this->sandboxBase.'/work',
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'active_hours_config' => [
                'start' => 'invalid',
                'end' => '17:00',
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('active_hours_config.start', $response->json('error.details'));
    }

    public function test_rejects_missing_days_in_active_hours(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Test Job Missing Days',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'runner_type' => 'claude',
            'task_markdown_content' => '# Test Task',
            'working_directory' => $this->sandboxBase.'/work',
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('active_hours_config.days', $response->json('error.details'));
    }

    public function test_rejects_invalid_day_number(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/agent/api/v1/jobs', [
            'name' => 'Test Job Invalid Day',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'runner_type' => 'claude',
            'task_markdown_content' => '# Test Task',
            'working_directory' => $this->sandboxBase.'/work',
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
                'days' => [0, 8], // Invalid: ISO-8601 days are 1-7
            ],
        ]);

        $response->assertUnprocessable();
        $details = $response->json('error.details');
        $this->assertArrayHasKey('active_hours_config.days.0', $details);
        $this->assertArrayHasKey('active_hours_config.days.1', $details);
    }

    public function test_api_response_includes_active_hours(): void
    {
        Sanctum::actingAs($this->user);

        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'working_directory' => $this->sandboxBase.'/work',
            'active_hours_config' => [
                'start' => '09:00',
                'end' => '17:00',
                'days' => [1, 2, 3, 4, 5],
            ],
        ]);

        $response = $this->getJson("/agent/api/v1/jobs/{$job->id}");

        $response->assertOk()
            ->assertJsonPath('data.active_hours_config.start', '09:00')
            ->assertJsonPath('data.active_hours_config.end', '17:00')
            ->assertJsonPath('data.active_hours_config.days', [1, 2, 3, 4, 5]);
    }

    public function test_api_response_includes_null_active_hours_when_not_set(): void
    {
        Sanctum::actingAs($this->user);

        $job = AgentJob::factory()->create([
            'user_id' => $this->user->id,
            'working_directory' => $this->sandboxBase.'/work',
            'active_hours_config' => null,
        ]);

        $response = $this->getJson("/agent/api/v1/jobs/{$job->id}");

        $response->assertOk()
            ->assertJsonPath('data.active_hours_config', null);
    }
}
