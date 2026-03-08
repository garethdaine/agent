<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_defined(): void
    {
        $this->assertEquals('pending', DelegationTask::STATUS_PENDING);
        $this->assertEquals('blocked', DelegationTask::STATUS_BLOCKED);
        $this->assertEquals('ready', DelegationTask::STATUS_READY);
        $this->assertEquals('assigned', DelegationTask::STATUS_ASSIGNED);
        $this->assertEquals('running', DelegationTask::STATUS_RUNNING);
        $this->assertEquals('verifying', DelegationTask::STATUS_VERIFYING);
        $this->assertEquals('succeeded', DelegationTask::STATUS_SUCCEEDED);
        $this->assertEquals('failed', DelegationTask::STATUS_FAILED);
        $this->assertEquals('cancelled', DelegationTask::STATUS_CANCELLED);
    }

    public function test_belongs_to_graph(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $this->assertInstanceOf(DelegationGraph::class, $task->graph);
        $this->assertEquals($graph->id, $task->graph->id);
    }

    public function test_has_many_attempts(): void
    {
        $task = DelegationTask::factory()->create();
        $attempt1 = DelegationAttempt::factory()->create(['delegation_task_id' => $task->id]);
        $attempt2 = DelegationAttempt::factory()->create(['delegation_task_id' => $task->id]);

        $task->refresh();

        $this->assertCount(2, $task->attempts);
        $this->assertTrue($task->attempts->contains($attempt1));
        $this->assertTrue($task->attempts->contains($attempt2));
    }

    public function test_belongs_to_assigned_profile_nullable(): void
    {
        $profile = DelegateeProfile::factory()->create();
        $taskWithProfile = DelegationTask::factory()->create(['assigned_delegatee_profile_id' => $profile->id]);
        $taskWithoutProfile = DelegationTask::factory()->create(['assigned_delegatee_profile_id' => null]);

        $this->assertInstanceOf(DelegateeProfile::class, $taskWithProfile->assignedProfile);
        $this->assertEquals($profile->id, $taskWithProfile->assignedProfile->id);
        $this->assertNull($taskWithoutProfile->assignedProfile);
    }

    public function test_dependencies_relationship(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task1 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id, 'sequence_order' => 0]);
        $task2 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id, 'sequence_order' => 1]);
        $task3 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id, 'sequence_order' => 1]);

        // task2 depends on task1, task3 depends on task1
        $task2->dependencies()->attach($task1->id);
        $task3->dependencies()->attach($task1->id);

        $task2->refresh();
        $task3->refresh();

        $this->assertCount(1, $task2->dependencies);
        $this->assertTrue($task2->dependencies->contains($task1));
        $this->assertCount(1, $task3->dependencies);
        $this->assertTrue($task3->dependencies->contains($task1));
    }

    public function test_dependents_relationship(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task1 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id, 'sequence_order' => 0]);
        $task2 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id, 'sequence_order' => 1]);
        $task3 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id, 'sequence_order' => 1]);

        // task2 and task3 depend on task1
        $task2->dependencies()->attach($task1->id);
        $task3->dependencies()->attach($task1->id);

        $task1->refresh();

        $this->assertCount(2, $task1->dependents);
        $this->assertTrue($task1->dependents->contains($task2));
        $this->assertTrue($task1->dependents->contains($task3));
    }

    public function test_casts_contract_json(): void
    {
        $task = DelegationTask::factory()->create([
            'contract_json' => [
                'required_capability' => 'code_execution',
                'authority_scope' => ['max_runtime_seconds' => 3600],
            ],
        ]);

        $task->refresh();

        $this->assertIsArray($task->contract_json);
        $this->assertEquals('code_execution', $task->contract_json['required_capability']);
        $this->assertEquals(3600, $task->contract_json['authority_scope']['max_runtime_seconds']);
    }

    public function test_casts_assignment_reason_json(): void
    {
        $task = DelegationTask::factory()->create([
            'assignment_reason_json' => [
                'score' => 0.95,
                'reason' => 'Highest success rate in 24h window',
            ],
        ]);

        $task->refresh();

        $this->assertIsArray($task->assignment_reason_json);
        $this->assertEquals(0.95, $task->assignment_reason_json['score']);
    }

    public function test_casts_metadata_json(): void
    {
        $task = DelegationTask::factory()->create([
            'metadata_json' => ['scope_warnings' => ['max_runtime_seconds reduced from 7200 to 3600']],
        ]);

        $task->refresh();

        $this->assertIsArray($task->metadata_json);
        $this->assertArrayHasKey('scope_warnings', $task->metadata_json);
    }

    public function test_casts_timestamps(): void
    {
        $now = now();
        $task = DelegationTask::factory()->create([
            'started_at' => $now,
            'finished_at' => $now->addMinutes(30),
        ]);

        $task->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->finished_at);
    }

    public function test_casts_sequence_order(): void
    {
        $task = DelegationTask::factory()->create(['sequence_order' => 5]);

        $task->refresh();

        $this->assertIsInt($task->sequence_order);
        $this->assertEquals(5, $task->sequence_order);
    }
}
