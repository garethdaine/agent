<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationGraphControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('delegation.enabled', true);
    }

    public function test_index_returns_user_graphs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userGraph = DelegationGraph::factory()->create(['user_id' => $user->id]);
        $otherGraph = DelegationGraph::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/graphs');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $userGraph->id)
            ->assertJsonMissing(['id' => $otherGraph->id]);
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();

        $draftGraph = DelegationGraph::factory()->create(['user_id' => $user->id, 'status' => DelegationGraph::STATUS_DRAFT]);
        $runningGraph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);
        $succeededGraph = DelegationGraph::factory()->succeeded()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/graphs?status=running');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $runningGraph->id);

        $response = $this->getJson('/agent/api/v1/delegation/graphs?status=draft');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $draftGraph->id);
    }

    public function test_store_creates_graph_from_linear_chain(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/agent/api/v1/delegation/graphs', [
            'name' => 'Test Linear Chain',
            'tasks' => [
                ['name' => 'Task 1', 'contract' => ['required_capability' => 'code_generation', 'prompt' => 'Do task 1']],
                ['name' => 'Task 2', 'contract' => ['required_capability' => 'testing', 'prompt' => 'Do task 2']],
            ],
            'format' => 'linear_chain',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Linear Chain')
            ->assertJsonPath('data.status', DelegationGraph::STATUS_DRAFT);

        $this->assertDatabaseHas('delegation_graphs', [
            'user_id' => $user->id,
            'name' => 'Test Linear Chain',
        ]);
    }

    public function test_store_creates_graph_from_dag_json(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/agent/api/v1/delegation/graphs', [
            'name' => 'Test DAG',
            'tasks' => [
                ['name' => 'Task A', 'contract' => ['required_capability' => 'code_generation', 'prompt' => 'Do A'], 'depends_on' => []],
                ['name' => 'Task B', 'contract' => ['required_capability' => 'testing', 'prompt' => 'Do B'], 'depends_on' => ['Task A']],
            ],
            'format' => 'dag',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test DAG');
    }

    public function test_store_returns_validation_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/agent/api/v1/delegation/graphs', [
            'name' => '',
            'tasks' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_show_returns_graph_with_tasks(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $user->id]);
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/graphs/{$graph->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $graph->id)
            ->assertJsonCount(1, 'data.tasks');
    }

    public function test_show_denies_other_user_graph(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/graphs/{$graph->id}");

        $response->assertNotFound();
    }

    public function test_update_only_allowed_for_draft(): void
    {
        $user = User::factory()->create();
        $draftGraph = DelegationGraph::factory()->create(['user_id' => $user->id, 'status' => DelegationGraph::STATUS_DRAFT]);
        $runningGraph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        // Draft graph can be updated
        $response = $this->putJson("/agent/api/v1/delegation/graphs/{$draftGraph->id}", [
            'name' => 'Updated Name',
        ]);
        $response->assertOk();

        // Running graph cannot be updated
        $response = $this->putJson("/agent/api/v1/delegation/graphs/{$runningGraph->id}", [
            'name' => 'Updated Name',
        ]);
        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_delete_only_allowed_for_terminal(): void
    {
        $user = User::factory()->create();
        $runningGraph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);
        $succeededGraph = DelegationGraph::factory()->succeeded()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        // Running graph cannot be deleted
        $response = $this->deleteJson("/agent/api/v1/delegation/graphs/{$runningGraph->id}");
        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE');

        // Terminal graph can be deleted
        $response = $this->deleteJson("/agent/api/v1/delegation/graphs/{$succeededGraph->id}");
        $response->assertOk();
    }

    public function test_start_transitions_ready_to_running(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->ready()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/delegation/graphs/{$graph->id}/start");

        $response->assertStatus(202)
            ->assertJsonPath('data.status', DelegationGraph::STATUS_RUNNING);

        $this->assertDatabaseHas('delegation_graphs', [
            'id' => $graph->id,
            'status' => DelegationGraph::STATUS_RUNNING,
        ]);
    }

    public function test_cancel_initiates_graceful_cancellation(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/delegation/graphs/{$graph->id}/cancel");

        $response->assertStatus(202)
            ->assertJsonPath('data.status', DelegationGraph::STATUS_CANCELLED);
    }

    public function test_clone_creates_copy_with_history(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->succeeded()->create(['user_id' => $user->id]);
        DelegationTask::factory()->succeeded()->create(['delegation_graph_id' => $graph->id]);

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/delegation/graphs/{$graph->id}/clone", [
            'mode' => 'all',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', DelegationGraph::STATUS_DRAFT);

        $this->assertDatabaseCount('delegation_graphs', 2);
    }

    public function test_clone_failed_subtree_mode(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->failed()->create(['user_id' => $user->id]);
        DelegationTask::factory()->succeeded()->create(['delegation_graph_id' => $graph->id, 'name' => 'Success Task']);
        DelegationTask::factory()->failed()->create(['delegation_graph_id' => $graph->id, 'name' => 'Failed Task']);

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/delegation/graphs/{$graph->id}/clone", [
            'mode' => 'failed_subtree',
        ]);

        $response->assertStatus(201);
    }

    public function test_feature_gate_blocks_when_disabled(): void
    {
        config()->set('delegation.enabled', false);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/delegation/graphs');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }

    public function test_events_returns_graph_events(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/graphs/{$graph->id}/events");

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_validate_endpoint_dry_runs_validation(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/delegation/graphs/{$graph->id}/validate");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['valid', 'errors', 'warnings']]);
    }

    public function test_restore_restores_soft_deleted_graph(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->succeeded()->create(['user_id' => $user->id]);
        $graph->delete();

        $this->actingAs($user);

        $response = $this->postJson("/agent/api/v1/delegation/graphs/{$graph->id}/restore");

        $response->assertOk();
        $this->assertNull($graph->fresh()->deleted_at);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/agent/api/v1/delegation/graphs');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
