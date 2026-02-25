<?php

namespace Tests\Unit\Support\Delegation;

use App\Exceptions\DelegationGraphCycleException;
use App\Exceptions\DelegationGraphTaskLimitException;
use App\Models\DelegationTask;
use App\Models\User;
use App\Support\Delegation\DelegationGraphBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationGraphBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_linear_chain_with_implicit_dependencies(): void
    {
        $user = User::factory()->create();
        $input = [
            ['name' => 'task1', 'contract' => ['prompt' => 'Do A']],
            ['name' => 'task2', 'contract' => ['prompt' => 'Do B']],
            ['name' => 'task3', 'contract' => ['prompt' => 'Do C']],
        ];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals(3, $graph->tasks()->count());
        $task2 = $graph->tasks()->where('name', 'task2')->first();
        $this->assertCount(1, $task2->dependencies);
        $this->assertEquals('task1', $task2->dependencies->first()->name);
    }

    public function test_builds_dag_with_explicit_dependencies(): void
    {
        $user = User::factory()->create();
        $input = [
            'tasks' => [
                ['name' => 'root', 'contract' => ['prompt' => 'Start'], 'depends_on' => []],
                ['name' => 'branch1', 'contract' => ['prompt' => 'B1'], 'depends_on' => ['root']],
                ['name' => 'branch2', 'contract' => ['prompt' => 'B2'], 'depends_on' => ['root']],
                ['name' => 'merge', 'contract' => ['prompt' => 'End'], 'depends_on' => ['branch1', 'branch2']],
            ],
        ];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals(4, $graph->tasks()->count());
        $merge = $graph->tasks()->where('name', 'merge')->first();
        $this->assertCount(2, $merge->dependencies);
    }

    public function test_throws_exception_on_cycle(): void
    {
        $this->expectException(DelegationGraphCycleException::class);

        $user = User::factory()->create();
        $input = [
            'tasks' => [
                ['name' => 'a', 'contract' => [], 'depends_on' => ['c']],
                ['name' => 'b', 'contract' => [], 'depends_on' => ['a']],
                ['name' => 'c', 'contract' => [], 'depends_on' => ['b']],
            ],
        ];

        (new DelegationGraphBuilder())->build($user, $input);
    }

    public function test_throws_exception_exceeding_max_tasks(): void
    {
        $this->expectException(DelegationGraphTaskLimitException::class);

        $user = User::factory()->create();
        $input = array_map(fn ($i) => ['name' => "task{$i}", 'contract' => []], range(1, 26));

        (new DelegationGraphBuilder())->build($user, $input);
    }

    public function test_assigns_sequence_order_from_topological_depth(): void
    {
        $user = User::factory()->create();
        $input = [
            'tasks' => [
                ['name' => 'root', 'contract' => [], 'depends_on' => []],
                ['name' => 'level1', 'contract' => [], 'depends_on' => ['root']],
                ['name' => 'level2', 'contract' => [], 'depends_on' => ['level1']],
            ],
        ];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals(0, $graph->tasks()->where('name', 'root')->first()->sequence_order);
        $this->assertEquals(1, $graph->tasks()->where('name', 'level1')->first()->sequence_order);
        $this->assertEquals(2, $graph->tasks()->where('name', 'level2')->first()->sequence_order);
    }

    public function test_root_tasks_set_to_ready_status(): void
    {
        $user = User::factory()->create();
        $input = [['name' => 'root', 'contract' => []]];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals(DelegationTask::STATUS_READY, $graph->tasks()->first()->status);
    }

    public function test_dependent_tasks_set_to_pending_status(): void
    {
        $user = User::factory()->create();
        $input = [
            'tasks' => [
                ['name' => 'root', 'contract' => [], 'depends_on' => []],
                ['name' => 'child', 'contract' => [], 'depends_on' => ['root']],
            ],
        ];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals(DelegationTask::STATUS_READY, $graph->tasks()->where('name', 'root')->first()->status);
        $this->assertEquals(DelegationTask::STATUS_PENDING, $graph->tasks()->where('name', 'child')->first()->status);
    }

    public function test_dag_with_diamond_dependency_pattern(): void
    {
        $user = User::factory()->create();
        // Diamond: A -> B, A -> C, B -> D, C -> D
        $input = [
            'tasks' => [
                ['name' => 'A', 'contract' => ['prompt' => 'Start'], 'depends_on' => []],
                ['name' => 'B', 'contract' => ['prompt' => 'Left'], 'depends_on' => ['A']],
                ['name' => 'C', 'contract' => ['prompt' => 'Right'], 'depends_on' => ['A']],
                ['name' => 'D', 'contract' => ['prompt' => 'Merge'], 'depends_on' => ['B', 'C']],
            ],
        ];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals(4, $graph->tasks()->count());

        // A should be at depth 0, B and C at depth 1, D at depth 2
        $this->assertEquals(0, $graph->tasks()->where('name', 'A')->first()->sequence_order);
        $this->assertEquals(1, $graph->tasks()->where('name', 'B')->first()->sequence_order);
        $this->assertEquals(1, $graph->tasks()->where('name', 'C')->first()->sequence_order);
        $this->assertEquals(2, $graph->tasks()->where('name', 'D')->first()->sequence_order);
    }

    public function test_single_task_is_valid_graph(): void
    {
        $user = User::factory()->create();
        $input = [['name' => 'only_task', 'contract' => ['prompt' => 'Do something']]];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals(1, $graph->tasks()->count());
        $this->assertEquals(0, $graph->tasks()->first()->sequence_order);
        $this->assertEquals(DelegationTask::STATUS_READY, $graph->tasks()->first()->status);
    }

    public function test_self_referencing_task_throws_cycle_exception(): void
    {
        $this->expectException(DelegationGraphCycleException::class);

        $user = User::factory()->create();
        $input = [
            'tasks' => [
                ['name' => 'self_ref', 'contract' => [], 'depends_on' => ['self_ref']],
            ],
        ];

        (new DelegationGraphBuilder())->build($user, $input);
    }

    public function test_exactly_max_tasks_allowed(): void
    {
        $user = User::factory()->create();
        $maxTasks = config('delegation.max_tasks_per_graph', 25);
        $input = array_map(fn ($i) => ['name' => "task{$i}", 'contract' => []], range(1, $maxTasks));

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals($maxTasks, $graph->tasks()->count());
    }

    public function test_graph_created_with_draft_status(): void
    {
        $user = User::factory()->create();
        $input = [['name' => 'task1', 'contract' => []]];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals('draft', $graph->status);
    }

    public function test_graph_assigned_to_correct_user(): void
    {
        $user = User::factory()->create();
        $input = [['name' => 'task1', 'contract' => []]];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals($user->id, $graph->user_id);
    }

    public function test_contract_json_preserved_in_tasks(): void
    {
        $user = User::factory()->create();
        $contract = ['prompt' => 'Do something', 'required_capability' => 'code_execution'];
        $input = [['name' => 'task1', 'contract' => $contract]];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $this->assertEquals($contract, $graph->tasks()->first()->contract_json);
    }

    public function test_multiple_root_tasks_all_set_to_ready(): void
    {
        $user = User::factory()->create();
        $input = [
            'tasks' => [
                ['name' => 'root1', 'contract' => [], 'depends_on' => []],
                ['name' => 'root2', 'contract' => [], 'depends_on' => []],
                ['name' => 'child', 'contract' => [], 'depends_on' => ['root1', 'root2']],
            ],
        ];

        $graph = (new DelegationGraphBuilder())->build($user, $input);

        $root1 = $graph->tasks()->where('name', 'root1')->first();
        $root2 = $graph->tasks()->where('name', 'root2')->first();
        $child = $graph->tasks()->where('name', 'child')->first();

        $this->assertEquals(DelegationTask::STATUS_READY, $root1->status);
        $this->assertEquals(DelegationTask::STATUS_READY, $root2->status);
        $this->assertEquals(DelegationTask::STATUS_PENDING, $child->status);
    }
}
