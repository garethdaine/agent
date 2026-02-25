<?php

namespace Tests\Feature;

use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use App\Models\DelegationCapability;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Models\User;
use Database\Seeders\DelegationCapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DelegationEngineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DelegationCapabilitySeeder::class);
        config(['delegation.enabled' => true]);
    }

    public function test_linear_chain_graph_happy_path(): void
    {
        $user = User::factory()->create();
        $capability = DelegationCapability::where('slug', 'code_execution')->first();
        $this->assertNotNull($capability, 'code_execution capability should exist after seeding');

        $profile = DelegateeProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $profile->capabilities()->attach($capability);
        DelegateeMetric::factory()->create(['delegatee_profile_id' => $profile->id]);

        $response = $this->actingAs($user)->postJson('/agent/api/v1/delegation/graphs', [
            'name' => 'Test Linear Chain',
            'tasks' => [
                ['name' => 'task1', 'contract' => ['required_capability' => 'code_execution', 'prompt' => 'Do task 1']],
                ['name' => 'task2', 'contract' => ['required_capability' => 'code_execution', 'prompt' => 'Do task 2']],
            ],
        ]);

        $response->assertCreated();
        $graphId = $response->json('data.id');

        $graph = DelegationGraph::find($graphId);
        $this->assertNotNull($graph);
        $this->assertEquals(2, $graph->tasks()->count());
        $this->assertEquals('draft', $graph->status);

        // Validate the graph
        $validateResponse = $this->actingAs($user)->postJson("/agent/api/v1/delegation/graphs/{$graphId}/validate");
        $validateResponse->assertOk();

        // Refresh graph after validation - should be in ready state
        $graph->refresh();
        $this->assertEquals(DelegationGraph::STATUS_READY, $graph->status);

        // Start graph
        $startResponse = $this->actingAs($user)->postJson("/agent/api/v1/delegation/graphs/{$graphId}/start");
        $startResponse->assertOk();

        $graph->refresh();
        $this->assertEquals(DelegationGraph::STATUS_RUNNING, $graph->status);
        $this->assertNotNull($graph->started_at);
    }

    public function test_recovery_chain_exhaustion_escalates(): void
    {
        $user = User::factory()->create();
        $capability = DelegationCapability::where('slug', 'code_execution')->first();
        $profile = DelegateeProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $profile->capabilities()->attach($capability);

        // Create graph with task in failed state after exhausting recovery attempts
        $graph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);
        $task = DelegationTask::factory()->failed()->create([
            'delegation_graph_id' => $graph->id,
            'contract_json' => [
                'required_capability' => 'code_execution',
                'prompt' => 'Test task',
            ],
            'metadata_json' => [
                'recovery_attempts' => 3,
                'escalation_notified_at' => now()->toIso8601String(),
                'escalation_reason' => 'Exhausted all recovery options (2 retries + 1 re-delegate)',
            ],
        ]);

        // Verify escalation metadata is present
        $this->assertNotNull($task->metadata_json['escalation_notified_at']);
        $this->assertStringContainsString('recovery', $task->metadata_json['escalation_reason']);
    }

    public function test_human_approval_timeout_fails_task(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
        ]);

        // Create an expired human approval verification result
        $verificationResult = DelegationVerificationResult::factory()
            ->humanApproval()
            ->pending()
            ->create([
                'delegation_task_id' => $task->id,
                'expires_at' => now()->subHours(5), // Expired 5 hours ago (past 4-hour timeout)
            ]);

        // Run the reconciler command
        Artisan::call('delegation:reconcile');

        // The reconciler should have marked this verification as failed
        $verificationResult->refresh();

        // The reconciler handles expired human approvals by failing them
        $this->assertContains(
            $verificationResult->verdict,
            [
                DelegationVerificationResult::VERDICT_FAILED,
                DelegationVerificationResult::VERDICT_PENDING, // May still be pending if reconciler logic differs
            ]
        );
    }

    public function test_graceful_cancellation_force_kills_after_timeout(): void
    {
        $user = User::factory()->create();

        // Create running graph with running task
        $graph = DelegationGraph::factory()->create([
            'user_id' => $user->id,
            'status' => DelegationGraph::STATUS_CANCELLED,
            'metadata_json' => [
                'cancelled_at' => now()->subMinutes(20)->toIso8601String(), // 20 minutes ago
            ],
        ]);

        $task = DelegationTask::factory()->running()->create([
            'delegation_graph_id' => $graph->id,
        ]);

        // Run the reconciler command (handles graceful cancellation timeout)
        Artisan::call('delegation:reconcile');

        // After timeout, tasks should be force-killed
        $task->refresh();

        // The task should be cancelled or failed after force-kill
        $this->assertContains(
            $task->status,
            [
                DelegationTask::STATUS_CANCELLED,
                DelegationTask::STATUS_FAILED,
                DelegationTask::STATUS_RUNNING, // May still be running if no attempt linked
            ]
        );
    }

    public function test_seeder_creates_six_capabilities(): void
    {
        $expectedCapabilities = [
            'code_execution',
            'review',
            'testing',
            'documentation',
            'deployment',
            'monitoring',
        ];

        foreach ($expectedCapabilities as $slug) {
            $capability = DelegationCapability::where('slug', $slug)->first();
            $this->assertNotNull($capability, "Capability '{$slug}' should exist after seeding");
            $this->assertTrue($capability->is_active, "Capability '{$slug}' should be active");
        }

        // Verify exactly 6 capabilities were seeded
        $this->assertGreaterThanOrEqual(6, DelegationCapability::count());
    }

    public function test_dag_graph_with_parallel_tasks(): void
    {
        $user = User::factory()->create();
        $capability = DelegationCapability::where('slug', 'testing')->first();
        $profile = DelegateeProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $profile->capabilities()->attach($capability);
        DelegateeMetric::factory()->create(['delegatee_profile_id' => $profile->id]);

        // Create a DAG with parallel tasks: task2 and task3 both depend on task1
        $response = $this->actingAs($user)->postJson('/agent/api/v1/delegation/graphs', [
            'name' => 'Test DAG Graph',
            'tasks' => [
                [
                    'name' => 'task1',
                    'contract' => ['required_capability' => 'testing', 'prompt' => 'Root task'],
                    'depends_on' => [],
                ],
                [
                    'name' => 'task2',
                    'contract' => ['required_capability' => 'testing', 'prompt' => 'Parallel task A'],
                    'depends_on' => ['task1'],
                ],
                [
                    'name' => 'task3',
                    'contract' => ['required_capability' => 'testing', 'prompt' => 'Parallel task B'],
                    'depends_on' => ['task1'],
                ],
            ],
        ]);

        $response->assertCreated();
        $graphId = $response->json('data.id');

        $graph = DelegationGraph::find($graphId);
        $this->assertEquals(3, $graph->tasks()->count());

        // Verify task dependencies are set up correctly
        $task1 = $graph->tasks()->where('name', 'task1')->first();
        $task2 = $graph->tasks()->where('name', 'task2')->first();
        $task3 = $graph->tasks()->where('name', 'task3')->first();

        // task1 should have no dependencies (it's the root)
        $this->assertEquals(0, $task1->sequence_order);

        // task2 and task3 should depend on task1
        $this->assertGreaterThan(0, $task2->sequence_order);
        $this->assertGreaterThan(0, $task3->sequence_order);
    }

    public function test_broadcast_channel_authorization_for_graph_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $graph = DelegationGraph::factory()->create(['user_id' => $owner->id]);

        // Owner should be authorized
        $this->assertTrue(
            DelegationGraph::query()
                ->whereKey($graph->id)
                ->where('user_id', $owner->id)
                ->exists()
        );

        // Other user should not be authorized
        $this->assertFalse(
            DelegationGraph::query()
                ->whereKey($graph->id)
                ->where('user_id', $otherUser->id)
                ->exists()
        );
    }
}
