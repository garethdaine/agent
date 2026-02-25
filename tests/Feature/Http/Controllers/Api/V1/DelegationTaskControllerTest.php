<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationVerificationResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('delegation.enabled', true);
    }

    public function test_index_returns_tasks_for_graph(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $user->id]);
        $task1 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id, 'name' => 'Task 1']);
        $task2 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id, 'name' => 'Task 2']);

        $otherGraph = DelegationGraph::factory()->create(['user_id' => $user->id]);
        $otherTask = DelegationTask::factory()->create(['delegation_graph_id' => $otherGraph->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/graphs/{$graph->id}/tasks");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $otherTask->id]);
    }

    public function test_index_denies_other_user_graph(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/graphs/{$graph->id}/tasks");

        $response->assertNotFound();
    }

    public function test_show_returns_task_with_attempts_and_verification(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $user->id]);
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);
        $attempt = DelegationAttempt::factory()->create(['delegation_task_id' => $task->id]);
        $verification = DelegationVerificationResult::factory()->create(['delegation_task_id' => $task->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/graphs/{$graph->id}/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'status',
                    'attempts',
                    'verification_results',
                ],
            ]);
    }

    public function test_show_denies_task_from_other_user_graph(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $otherUser->id]);
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/graphs/{$graph->id}/tasks/{$task->id}");

        $response->assertNotFound();
    }

    public function test_resolve_verification_resolves_pending_approval(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
        ]);
        $verification = DelegationVerificationResult::factory()->create([
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(
            "/agent/api/v1/delegation/graphs/{$graph->id}/tasks/{$task->id}/verification/resolve",
            [
                'verification_result_id' => $verification->id,
                'verdict' => 'passed',
                'evidence' => ['notes' => 'Approved by user'],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.verdict', 'passed');

        $this->assertDatabaseHas('delegation_verification_results', [
            'id' => $verification->id,
            'verdict' => DelegationVerificationResult::VERDICT_PASSED,
        ]);
    }

    public function test_resolve_verification_rejects_non_pending(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
        ]);
        $verification = DelegationVerificationResult::factory()->create([
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
            'verdict' => DelegationVerificationResult::VERDICT_PASSED,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(
            "/agent/api/v1/delegation/graphs/{$graph->id}/tasks/{$task->id}/verification/resolve",
            [
                'verification_result_id' => $verification->id,
                'verdict' => 'passed',
            ]
        );

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_resolve_verification_validates_verdict(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->running()->create(['user_id' => $user->id]);
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => DelegationTask::STATUS_VERIFYING,
        ]);
        $verification = DelegationVerificationResult::factory()->create([
            'delegation_task_id' => $task->id,
            'step_type' => DelegationVerificationResult::STEP_TYPE_HUMAN_APPROVAL,
            'verdict' => DelegationVerificationResult::VERDICT_PENDING,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(
            "/agent/api/v1/delegation/graphs/{$graph->id}/tasks/{$task->id}/verification/resolve",
            [
                'verification_result_id' => $verification->id,
                'verdict' => 'invalid_verdict',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_feature_gate_blocks_when_disabled(): void
    {
        config()->set('delegation.enabled', false);

        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/delegation/graphs/{$graph->id}/tasks");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/agent/api/v1/delegation/graphs/1/tasks');

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
