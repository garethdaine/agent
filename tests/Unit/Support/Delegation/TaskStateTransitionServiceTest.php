<?php

namespace Tests\Unit\Support\Delegation;

use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Support\Delegation\TaskStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskStateTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_succeeds_from_valid_status(): void
    {
        $task = DelegationTask::factory()->create(['status' => 'pending']);
        $service = new TaskStateTransitionService;

        $result = $service->transition($task->id, ['pending'], 'ready');

        $this->assertTrue($result);
        $this->assertEquals('ready', $task->fresh()->status);
    }

    public function test_transition_fails_from_invalid_status(): void
    {
        $task = DelegationTask::factory()->create(['status' => 'running']);
        $service = new TaskStateTransitionService;

        $result = $service->transition($task->id, ['pending'], 'ready');

        $this->assertFalse($result);
        $this->assertEquals('running', $task->fresh()->status);
    }

    public function test_transition_sets_additional_attributes(): void
    {
        $task = DelegationTask::factory()->create(['status' => 'ready']);
        $service = new TaskStateTransitionService;

        $result = $service->transition($task->id, ['ready'], 'running', ['started_at' => now()]);

        $this->assertTrue($result);
        $this->assertNotNull($task->fresh()->started_at);
    }

    public function test_transition_fails_for_nonexistent_task(): void
    {
        $service = new TaskStateTransitionService;

        $result = $service->transition(99999, ['pending'], 'ready');

        $this->assertFalse($result);
    }

    public function test_transition_fails_with_empty_from_statuses(): void
    {
        $task = DelegationTask::factory()->create(['status' => 'pending']);
        $service = new TaskStateTransitionService;

        $result = $service->transition($task->id, [], 'ready');

        $this->assertFalse($result);
        $this->assertEquals('pending', $task->fresh()->status);
    }

    public function test_transition_accepts_multiple_from_statuses(): void
    {
        $task = DelegationTask::factory()->create(['status' => 'blocked']);
        $service = new TaskStateTransitionService;

        $result = $service->transition($task->id, ['pending', 'blocked'], 'ready');

        $this->assertTrue($result);
        $this->assertEquals('ready', $task->fresh()->status);
    }

    public function test_concurrent_update_only_one_succeeds(): void
    {
        $task = DelegationTask::factory()->create(['status' => 'pending']);
        $service = new TaskStateTransitionService;

        // First transition succeeds
        $result1 = $service->transition($task->id, ['pending'], 'ready');

        // Second transition with same from status fails (status already changed)
        $result2 = $service->transition($task->id, ['pending'], 'blocked');

        $this->assertTrue($result1);
        $this->assertFalse($result2);
        $this->assertEquals('ready', $task->fresh()->status);
    }

    public function test_transition_updates_timestamp(): void
    {
        $this->freezeTime();
        $task = DelegationTask::factory()->create(['status' => 'pending']);
        $originalUpdatedAt = $task->updated_at;

        // Travel forward in time to ensure timestamp difference
        $this->travel(5)->seconds();

        $service = new TaskStateTransitionService;
        $result = $service->transition($task->id, ['pending'], 'ready');

        $this->assertTrue($result);
        $this->assertGreaterThan($originalUpdatedAt, $task->fresh()->updated_at);
    }

    public function test_transition_with_error_attributes(): void
    {
        $task = DelegationTask::factory()->running()->create();
        $service = new TaskStateTransitionService;

        $result = $service->transition(
            $task->id,
            ['running'],
            'failed',
            [
                'error_code' => 'EXECUTION_FAILED',
                'error_summary' => 'Task execution failed.',
                'finished_at' => now(),
            ]
        );

        $this->assertTrue($result);
        $freshTask = $task->fresh();
        $this->assertEquals('failed', $freshTask->status);
        $this->assertEquals('EXECUTION_FAILED', $freshTask->error_code);
        $this->assertEquals('Task execution failed.', $freshTask->error_summary);
        $this->assertNotNull($freshTask->finished_at);
    }

    public function test_transition_through_full_lifecycle(): void
    {
        $task = DelegationTask::factory()->create(['status' => DelegationTask::STATUS_PENDING]);
        $service = new TaskStateTransitionService;

        // pending -> ready
        $this->assertTrue($service->transition($task->id, [DelegationTask::STATUS_PENDING], DelegationTask::STATUS_READY));
        $this->assertEquals(DelegationTask::STATUS_READY, $task->fresh()->status);

        // ready -> assigned
        $this->assertTrue($service->transition($task->id, [DelegationTask::STATUS_READY], DelegationTask::STATUS_ASSIGNED));
        $this->assertEquals(DelegationTask::STATUS_ASSIGNED, $task->fresh()->status);

        // assigned -> running
        $this->assertTrue($service->transition($task->id, [DelegationTask::STATUS_ASSIGNED], DelegationTask::STATUS_RUNNING, ['started_at' => now()]));
        $this->assertEquals(DelegationTask::STATUS_RUNNING, $task->fresh()->status);

        // running -> verifying
        $this->assertTrue($service->transition($task->id, [DelegationTask::STATUS_RUNNING], DelegationTask::STATUS_VERIFYING));
        $this->assertEquals(DelegationTask::STATUS_VERIFYING, $task->fresh()->status);

        // verifying -> succeeded
        $this->assertTrue($service->transition($task->id, [DelegationTask::STATUS_VERIFYING], DelegationTask::STATUS_SUCCEEDED, ['finished_at' => now()]));
        $this->assertEquals(DelegationTask::STATUS_SUCCEEDED, $task->fresh()->status);
    }

    public function test_transition_to_cancelled_from_multiple_states(): void
    {
        $service = new TaskStateTransitionService;

        // Can cancel from pending
        $task1 = DelegationTask::factory()->create(['status' => DelegationTask::STATUS_PENDING]);
        $this->assertTrue($service->transition($task1->id, [DelegationTask::STATUS_PENDING, DelegationTask::STATUS_READY, DelegationTask::STATUS_RUNNING], DelegationTask::STATUS_CANCELLED));
        $this->assertEquals(DelegationTask::STATUS_CANCELLED, $task1->fresh()->status);

        // Can cancel from ready
        $task2 = DelegationTask::factory()->create(['status' => DelegationTask::STATUS_READY]);
        $this->assertTrue($service->transition($task2->id, [DelegationTask::STATUS_PENDING, DelegationTask::STATUS_READY, DelegationTask::STATUS_RUNNING], DelegationTask::STATUS_CANCELLED));
        $this->assertEquals(DelegationTask::STATUS_CANCELLED, $task2->fresh()->status);

        // Can cancel from running
        $task3 = DelegationTask::factory()->running()->create();
        $this->assertTrue($service->transition($task3->id, [DelegationTask::STATUS_PENDING, DelegationTask::STATUS_READY, DelegationTask::STATUS_RUNNING], DelegationTask::STATUS_CANCELLED));
        $this->assertEquals(DelegationTask::STATUS_CANCELLED, $task3->fresh()->status);
    }

    public function test_transition_preserves_other_task_attributes(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $graph->id,
            'status' => 'pending',
            'name' => 'Test Task',
            'sequence_order' => 5,
            'contract_json' => ['key' => 'value'],
            'metadata_json' => ['custom' => 'data'],
        ]);
        $service = new TaskStateTransitionService;

        $service->transition($task->id, ['pending'], 'ready');

        $freshTask = $task->fresh();
        $this->assertEquals($graph->id, $freshTask->delegation_graph_id);
        $this->assertEquals('Test Task', $freshTask->name);
        $this->assertEquals(5, $freshTask->sequence_order);
        $this->assertEquals(['key' => 'value'], $freshTask->contract_json);
        $this->assertEquals(['custom' => 'data'], $freshTask->metadata_json);
    }
}
