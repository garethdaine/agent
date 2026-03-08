<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DelegationEvent;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_graph(): void
    {
        $graph = DelegationGraph::factory()->create();
        $event = DelegationEvent::factory()->create(['delegation_graph_id' => $graph->id]);

        $this->assertInstanceOf(DelegationGraph::class, $event->graph);
        $this->assertEquals($graph->id, $event->graph->id);
    }

    public function test_belongs_to_task_nullable(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);

        $eventWithTask = DelegationEvent::factory()->create([
            'delegation_graph_id' => $graph->id,
            'delegation_task_id' => $task->id,
        ]);
        $eventWithoutTask = DelegationEvent::factory()->create([
            'delegation_graph_id' => $graph->id,
            'delegation_task_id' => null,
        ]);

        $this->assertInstanceOf(DelegationTask::class, $eventWithTask->task);
        $this->assertEquals($task->id, $eventWithTask->task->id);
        $this->assertNull($eventWithoutTask->task);
    }

    public function test_casts_payload_json(): void
    {
        $event = DelegationEvent::factory()->create([
            'payload_json' => [
                'from_status' => 'running',
                'to_status' => 'succeeded',
                'metadata' => ['duration_ms' => 45000],
            ],
        ]);

        $event->refresh();

        $this->assertIsArray($event->payload_json);
        $this->assertEquals('running', $event->payload_json['from_status']);
        $this->assertEquals('succeeded', $event->payload_json['to_status']);
        $this->assertEquals(45000, $event->payload_json['metadata']['duration_ms']);
    }

    public function test_casts_event_ts(): void
    {
        $now = now();
        $event = DelegationEvent::factory()->create(['event_ts' => $now]);

        $event->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->event_ts);
    }

    public function test_casts_sequence(): void
    {
        $event = DelegationEvent::factory()->create(['sequence' => 42]);

        $event->refresh();

        $this->assertIsInt($event->sequence);
        $this->assertEquals(42, $event->sequence);
    }

    public function test_cascades_on_graph_delete(): void
    {
        $graph = DelegationGraph::factory()->create();
        $event = DelegationEvent::factory()->create(['delegation_graph_id' => $graph->id]);

        $eventId = $event->id;
        $graph->forceDelete();

        $this->assertNull(DelegationEvent::find($eventId));
    }

    public function test_cascades_on_task_delete(): void
    {
        $graph = DelegationGraph::factory()->create();
        $task = DelegationTask::factory()->create(['delegation_graph_id' => $graph->id]);
        $event = DelegationEvent::factory()->create([
            'delegation_graph_id' => $graph->id,
            'delegation_task_id' => $task->id,
        ]);

        $eventId = $event->id;
        $task->delete();

        $this->assertNull(DelegationEvent::find($eventId));
    }
}
