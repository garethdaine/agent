<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DelegationEvent;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_defined(): void
    {
        $this->assertEquals('draft', DelegationGraph::STATUS_DRAFT);
        $this->assertEquals('validating', DelegationGraph::STATUS_VALIDATING);
        $this->assertEquals('ready', DelegationGraph::STATUS_READY);
        $this->assertEquals('running', DelegationGraph::STATUS_RUNNING);
        $this->assertEquals('succeeded', DelegationGraph::STATUS_SUCCEEDED);
        $this->assertEquals('failed', DelegationGraph::STATUS_FAILED);
        $this->assertEquals('partial', DelegationGraph::STATUS_PARTIAL);
        $this->assertEquals('cancelled', DelegationGraph::STATUS_CANCELLED);
    }

    public function test_active_statuses_constant(): void
    {
        $this->assertIsArray(DelegationGraph::ACTIVE_STATUSES);
        $this->assertContains('running', DelegationGraph::ACTIVE_STATUSES);
        $this->assertCount(1, DelegationGraph::ACTIVE_STATUSES);
    }

    public function test_terminal_statuses_constant(): void
    {
        $this->assertIsArray(DelegationGraph::TERMINAL_STATUSES);
        $this->assertContains('succeeded', DelegationGraph::TERMINAL_STATUSES);
        $this->assertContains('failed', DelegationGraph::TERMINAL_STATUSES);
        $this->assertContains('partial', DelegationGraph::TERMINAL_STATUSES);
        $this->assertContains('cancelled', DelegationGraph::TERMINAL_STATUSES);
        $this->assertCount(4, DelegationGraph::TERMINAL_STATUSES);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $graph = DelegationGraph::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $graph->user);
        $this->assertEquals($user->id, $graph->user->id);
    }

    public function test_has_many_tasks(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task1 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);
        $task2 = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $graph->refresh();

        $this->assertCount(2, $graph->tasks);
        $this->assertTrue($graph->tasks->contains($task1));
        $this->assertTrue($graph->tasks->contains($task2));
    }

    public function test_has_many_events(): void
    {
        $graph = DelegationGraph::factory()->create();
        $event1 = DelegationEvent::factory()->create(['delegation_graph_id' => $graph->id]);
        $event2 = DelegationEvent::factory()->create(['delegation_graph_id' => $graph->id]);

        $graph->refresh();

        $this->assertCount(2, $graph->events);
        $this->assertTrue($graph->events->contains($event1));
        $this->assertTrue($graph->events->contains($event2));
    }

    public function test_scope_active(): void
    {
        $runningGraph = DelegationGraph::factory()->create(['status' => DelegationGraph::STATUS_RUNNING]);
        $draftGraph = DelegationGraph::factory()->create(['status' => DelegationGraph::STATUS_DRAFT]);
        $succeededGraph = DelegationGraph::factory()->create(['status' => DelegationGraph::STATUS_SUCCEEDED]);

        $activeGraphs = DelegationGraph::active()->get();

        $this->assertCount(1, $activeGraphs);
        $this->assertTrue($activeGraphs->contains($runningGraph));
        $this->assertFalse($activeGraphs->contains($draftGraph));
        $this->assertFalse($activeGraphs->contains($succeededGraph));
    }

    public function test_scope_terminal(): void
    {
        $runningGraph = DelegationGraph::factory()->create(['status' => DelegationGraph::STATUS_RUNNING]);
        $succeededGraph = DelegationGraph::factory()->create(['status' => DelegationGraph::STATUS_SUCCEEDED]);
        $failedGraph = DelegationGraph::factory()->create(['status' => DelegationGraph::STATUS_FAILED]);
        $partialGraph = DelegationGraph::factory()->create(['status' => DelegationGraph::STATUS_PARTIAL]);
        $cancelledGraph = DelegationGraph::factory()->create(['status' => DelegationGraph::STATUS_CANCELLED]);

        $terminalGraphs = DelegationGraph::terminal()->get();

        $this->assertCount(4, $terminalGraphs);
        $this->assertFalse($terminalGraphs->contains($runningGraph));
        $this->assertTrue($terminalGraphs->contains($succeededGraph));
        $this->assertTrue($terminalGraphs->contains($failedGraph));
        $this->assertTrue($terminalGraphs->contains($partialGraph));
        $this->assertTrue($terminalGraphs->contains($cancelledGraph));
    }

    public function test_casts_metadata_json(): void
    {
        $graph = DelegationGraph::factory()->create([
            'metadata_json' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $graph->refresh();

        $this->assertIsArray($graph->metadata_json);
        $this->assertEquals('value', $graph->metadata_json['key']);
        $this->assertEquals(1, $graph->metadata_json['nested']['a']);
    }

    public function test_casts_timestamps(): void
    {
        $now = now();
        $graph = DelegationGraph::factory()->create([
            'started_at' => $now,
            'finished_at' => $now->addHour(),
        ]);

        $graph->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $graph->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $graph->finished_at);
    }

    public function test_casts_max_parallel_tasks(): void
    {
        $graph = DelegationGraph::factory()->create(['max_parallel_tasks' => 10]);

        $graph->refresh();

        $this->assertIsInt($graph->max_parallel_tasks);
        $this->assertEquals(10, $graph->max_parallel_tasks);
    }

    public function test_uses_soft_deletes(): void
    {
        $graph = DelegationGraph::factory()->create();

        $graph->delete();

        $this->assertSoftDeleted($graph);
        $this->assertNotNull(DelegationGraph::withTrashed()->find($graph->id));
        $this->assertNull(DelegationGraph::find($graph->id));
    }
}
