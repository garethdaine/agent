<?php

namespace Tests\Feature\Support\Delegation;

use App\Jobs\DelegationAttemptCompletedJob;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJobRun;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use App\Support\Delegation\AttemptSpawner;
use App\Support\Delegation\ContractEnforcer;
use App\Support\Delegation\EnforcementResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class AttemptSpawnerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegateeProfile $profile;

    private DelegationGraph $graph;

    private DelegationTask $task;

    private ContractEnforcer $enforcer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->profile = DelegateeProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Profile',
            'runner_type' => 'claude',
            'command_template' => 'claude --task {task}',
            'working_directory' => '/var/www/project',
            'env_json' => ['CLAUDE_API_KEY' => 'test-key'],
            'is_active' => true,
        ]);

        $this->graph = DelegationGraph::factory()->running()->create([
            'user_id' => $this->user->id,
        ]);

        $this->task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $this->graph->id,
            'name' => 'Test Task',
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Implement feature X',
                'authority_scope' => [
                    'allowed_paths' => ['/var/www/project'],
                    'max_runtime_seconds' => 3600,
                ],
            ],
            'metadata_json' => [],
        ]);

        // Default mock that returns unchanged config
        $this->enforcer = Mockery::mock(ContractEnforcer::class);
        $this->enforcer
            ->shouldReceive('enforce')
            ->andReturn(EnforcementResult::unchanged($this->task->contract_json));
    }

    public function test_creates_delegation_attempt(): void
    {
        Bus::fake();

        $spawner = new AttemptSpawner($this->enforcer);
        $attempt = $spawner->spawn($this->task, $this->profile);

        $this->assertInstanceOf(DelegationAttempt::class, $attempt);
        $this->assertEquals(DelegationAttempt::STATUS_RUNNING, $attempt->status);
        $this->assertEquals($this->task->id, $attempt->delegation_task_id);
        $this->assertEquals($this->profile->id, $attempt->delegatee_profile_id);
        $this->assertEquals(1, $attempt->attempt_number);
        $this->assertNotNull($attempt->started_at);

        $this->assertDatabaseHas('delegation_attempts', [
            'id' => $attempt->id,
            'delegation_task_id' => $this->task->id,
            'delegatee_profile_id' => $this->profile->id,
            'status' => DelegationAttempt::STATUS_RUNNING,
            'attempt_number' => 1,
        ]);
    }

    public function test_increments_attempt_number(): void
    {
        Bus::fake();

        // Create a previous attempt
        DelegationAttempt::factory()->failed()->create([
            'delegation_task_id' => $this->task->id,
            'delegatee_profile_id' => $this->profile->id,
            'attempt_number' => 1,
        ]);

        $spawner = new AttemptSpawner($this->enforcer);
        $attempt = $spawner->spawn($this->task, $this->profile);

        $this->assertEquals(2, $attempt->attempt_number);
    }

    public function test_creates_agent_job_with_delegation_source(): void
    {
        Bus::fake();

        $spawner = new AttemptSpawner($this->enforcer);
        $attempt = $spawner->spawn($this->task, $this->profile);

        // Verify agent job was created with correct config from profile
        $run = AgentJobRun::find($attempt->agent_job_run_id);
        $this->assertNotNull($run);

        $agentJob = $run->job;
        $this->assertNotNull($agentJob);
        $this->assertEquals($this->user->id, $agentJob->user_id);
        $this->assertEquals("Delegation: {$this->task->name}", $agentJob->name);
        $this->assertEquals($this->profile->runner_type, $agentJob->runner_type);
        $this->assertEquals($this->profile->command_template, $agentJob->command_template);
        $this->assertEquals($this->profile->working_directory, $agentJob->working_directory);
        $this->assertEquals($this->profile->env_json, $agentJob->env_json);

        // Verify delegation source is in the run's metadata
        // Note: trigger_type uses 'manual' due to schema constraint; actual source is in metadata
        $this->assertEquals(AgentJobRun::TRIGGER_MANUAL, $run->trigger_type);
        $this->assertEquals('delegation', $run->metadata_json['source']);
        $this->assertEquals($this->task->id, $run->metadata_json['task_id']);
    }

    public function test_dispatches_execute_job_chain(): void
    {
        Bus::fake();

        $spawner = new AttemptSpawner($this->enforcer);
        $attempt = $spawner->spawn($this->task, $this->profile);

        // Verify the chain was dispatched
        Bus::assertChained([
            function (ExecuteAgentRunJob $job) use ($attempt) {
                return $job->runId === $attempt->agent_job_run_id;
            },
            function (DelegationAttemptCompletedJob $job) use ($attempt) {
                return $job->attemptId === $attempt->id;
            },
        ]);
    }

    public function test_links_attempt_to_agent_job_run(): void
    {
        Bus::fake();

        $spawner = new AttemptSpawner($this->enforcer);
        $attempt = $spawner->spawn($this->task, $this->profile);

        $this->assertNotNull($attempt->agent_job_run_id);

        $run = AgentJobRun::find($attempt->agent_job_run_id);
        $this->assertNotNull($run);
        $this->assertEquals(AgentJobRun::STATUS_QUEUED, $run->status);
    }

    public function test_injects_scope_warnings_into_task_metadata(): void
    {
        Bus::fake();

        // Mock enforcer to return narrowed result with warnings
        $enforcer = Mockery::mock(ContractEnforcer::class);
        $enforcer
            ->shouldReceive('enforce')
            ->once()
            ->andReturn(EnforcementResult::narrowed(
                ['authority_scope' => ['max_runtime_seconds' => 3600]],
                ['max_runtime_seconds capped from 7200 to 3600']
            ));

        $spawner = new AttemptSpawner($enforcer);
        $attempt = $spawner->spawn($this->task, $this->profile);

        $this->task->refresh();

        $this->assertArrayHasKey('scope_warnings', $this->task->metadata_json);
        $this->assertContains(
            'max_runtime_seconds capped from 7200 to 3600',
            $this->task->metadata_json['scope_warnings']
        );
    }

    public function test_preserves_existing_task_metadata_when_injecting_warnings(): void
    {
        Bus::fake();

        $this->task->update([
            'metadata_json' => ['existing_key' => 'existing_value'],
        ]);

        $enforcer = Mockery::mock(ContractEnforcer::class);
        $enforcer
            ->shouldReceive('enforce')
            ->once()
            ->andReturn(EnforcementResult::narrowed(
                ['authority_scope' => ['max_runtime_seconds' => 3600]],
                ['scope was narrowed']
            ));

        $spawner = new AttemptSpawner($enforcer);
        $spawner->spawn($this->task, $this->profile);

        $this->task->refresh();

        $this->assertArrayHasKey('existing_key', $this->task->metadata_json);
        $this->assertEquals('existing_value', $this->task->metadata_json['existing_key']);
        $this->assertArrayHasKey('scope_warnings', $this->task->metadata_json);
    }

    public function test_does_not_inject_warnings_when_scope_not_narrowed(): void
    {
        Bus::fake();

        $spawner = new AttemptSpawner($this->enforcer);
        $spawner->spawn($this->task, $this->profile);

        $this->task->refresh();

        $this->assertArrayNotHasKey('scope_warnings', $this->task->metadata_json ?? []);
    }
}
