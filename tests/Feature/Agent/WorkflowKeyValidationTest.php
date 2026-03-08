<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Models\AgentJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowKeyValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $sandboxBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandboxBase = storage_path('framework/testing/workflow-key');
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

    public function test_api_rejects_invalid_workflow_keys(): void
    {
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $invalidKeys = [
            'Eng.repo-analysis.v1',
            'eng.repo-analysis',
            '',
            'eng..repo.v1',
        ];

        foreach ($invalidKeys as $workflowKey) {
            $response = $this->postJson('/agent/api/v1/jobs', $this->validPayload([
                'workflow_key' => $workflowKey,
                'name' => 'Workflow key validation '.$workflowKey,
            ]));

            $response->assertStatus(422);
            $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
            $this->assertArrayHasKey('workflow_key', $response->json('error.details', []));
        }
    }

    public function test_api_accepts_valid_workflow_key_and_persists_it(): void
    {
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/agent/api/v1/jobs', $this->validPayload([
            'name' => 'Workflow key accepted',
            'workflow_key' => 'eng.repo-analysis.v1',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.workflow_key', 'eng.repo-analysis.v1');

        $job = AgentJob::query()->where('name', 'Workflow key accepted')->firstOrFail();
        $this->assertSame('eng.repo-analysis.v1', $job->workflow_key);
    }

    public function test_api_assigns_canonical_workflow_key_when_omitted(): void
    {
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/agent/api/v1/jobs', $this->validPayload([
            'name' => 'Legacy Workflow Name',
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.workflow_key', 'legacy-workflow-name.v1');

        $job = AgentJob::query()->where('name', 'Legacy Workflow Name')->firstOrFail();
        $this->assertSame('legacy-workflow-name.v1', $job->workflow_key);
    }

    public function test_runtime_selection_by_workflow_key_route_resolves_only_canonical_keys(): void
    {
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $job = AgentJob::query()->create([
            'user_id' => $user->id,
            'name' => 'Runtime Selection Job',
            'workflow_key' => 'eng.runtime-selection.v3',
            'description' => null,
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => config('agent.default_templates.claude'),
            'task_markdown_path' => $this->validPayload()['task_markdown_path'],
            'working_directory' => $this->sandboxBase.'/work',
        ]);

        $this->getJson('/agent/api/v1/jobs/by-workflow/eng.runtime-selection.v3')
            ->assertOk()
            ->assertJsonPath('data.id', $job->id)
            ->assertJsonPath('data.workflow_key', 'eng.runtime-selection.v3');

        $this->getJson('/agent/api/v1/jobs/by-workflow/Eng.runtime-selection.v3')
            ->assertNotFound();
    }

    public function test_api_rejects_duplicate_workflow_key_for_same_user(): void
    {
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/agent/api/v1/jobs', $this->validPayload([
            'name' => 'Original workflow key owner',
            'workflow_key' => 'eng.duplicate-check.v1',
        ]))->assertCreated();

        $response = $this->postJson('/agent/api/v1/jobs', $this->validPayload([
            'name' => 'Duplicate workflow key owner',
            'workflow_key' => 'eng.duplicate-check.v1',
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('workflow_key', $response->json('error.details', []));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $taskFile = $this->sandboxBase.'/tasks/workflow-key-task.md';
        if (! file_exists($taskFile)) {
            file_put_contents($taskFile, "# Workflow Key\n");
        }

        return array_merge([
            'name' => 'Workflow Key Job',
            'description' => 'workflow key contract test',
            'cron_expression' => '0 0 1 1 1',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 60,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'task_markdown_path' => $taskFile,
            'working_directory' => $this->sandboxBase.'/work',
        ], $overrides);
    }
}
