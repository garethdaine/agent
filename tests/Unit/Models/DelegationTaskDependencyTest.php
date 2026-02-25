<?php

namespace Tests\Unit\Models;

use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\DelegationTaskDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationTaskDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_task(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);
        $dependsOnTask = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $dependency = DelegationTaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTask->id,
        ]);

        $this->assertInstanceOf(DelegationTask::class, $dependency->task);
        $this->assertEquals($task->id, $dependency->task->id);
    }

    public function test_belongs_to_depends_on_task(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);
        $dependsOnTask = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $dependency = DelegationTaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTask->id,
        ]);

        $this->assertInstanceOf(DelegationTask::class, $dependency->dependsOnTask);
        $this->assertEquals($dependsOnTask->id, $dependency->dependsOnTask->id);
    }

    public function test_unique_constraint_on_task_and_depends_on(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);
        $dependsOnTask = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        DelegationTaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTask->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DelegationTaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTask->id,
        ]);
    }

    public function test_cascades_on_task_delete(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);
        $dependsOnTask = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $dependency = DelegationTaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTask->id,
        ]);

        $dependencyId = $dependency->id;
        $task->delete();

        $this->assertNull(DelegationTaskDependency::find($dependencyId));
    }

    public function test_cascades_on_depends_on_task_delete(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);
        $dependsOnTask = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $dependency = DelegationTaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTask->id,
        ]);

        $dependencyId = $dependency->id;
        $dependsOnTask->delete();

        $this->assertNull(DelegationTaskDependency::find($dependencyId));
    }
}
