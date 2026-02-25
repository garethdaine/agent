<?php

namespace Tests\Unit\Support;

use App\Models\DelegationEvent;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;
use App\Support\Delegation\DelegationEventWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationEventWriterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DelegationGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->graph = DelegationGraph::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_write_creates_event_with_correct_attributes(): void
    {
        $writer = new DelegationEventWriter($this->graph);

        $writer->write('graph.started', ['status' => 'running']);

        $event = DelegationEvent::query()->where('delegation_graph_id', $this->graph->id)->first();

        $this->assertNotNull($event);
        $this->assertSame('graph.started', $event->event_type);
        $this->assertSame(['status' => 'running'], $event->payload_json);
        $this->assertSame(1, $event->sequence);
        $this->assertNull($event->delegation_task_id);
        $this->assertNotNull($event->event_ts);
    }

    public function test_write_auto_increments_sequence_per_graph(): void
    {
        $writer = new DelegationEventWriter($this->graph);

        $writer->write('event.one', ['data' => 1]);
        $writer->write('event.two', ['data' => 2]);
        $writer->write('event.three', ['data' => 3]);

        $events = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->orderBy('sequence')
            ->get();

        $this->assertCount(3, $events);
        $this->assertSame(1, $events[0]->sequence);
        $this->assertSame(2, $events[1]->sequence);
        $this->assertSame(3, $events[2]->sequence);
    }

    public function test_write_continues_sequence_from_existing_events(): void
    {
        // Pre-create some events
        DelegationEvent::query()->create([
            'delegation_graph_id' => $this->graph->id,
            'event_type' => 'existing.event',
            'sequence' => 5,
            'payload_json' => [],
            'event_ts' => now(),
        ]);

        $writer = new DelegationEventWriter($this->graph);
        $writer->write('new.event', ['data' => 'test']);

        $newEvent = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->where('event_type', 'new.event')
            ->first();

        $this->assertSame(6, $newEvent->sequence);
    }

    public function test_write_attaches_task_when_provided(): void
    {
        $task = DelegationTask::factory()->create([
            'delegation_graph_id' => $this->graph->id,
        ]);

        $writer = new DelegationEventWriter($this->graph);
        $writer->write('task.started', ['task_name' => $task->name], $task);

        $event = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->first();

        $this->assertSame($task->id, $event->delegation_task_id);
    }

    public function test_write_handles_empty_payload(): void
    {
        $writer = new DelegationEventWriter($this->graph);
        $writer->write('lifecycle.event', []);

        $event = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame([], $event->payload_json);
    }

    public function test_sequences_are_isolated_per_graph(): void
    {
        $graph2 = DelegationGraph::factory()->create(['user_id' => $this->user->id]);

        $writer1 = new DelegationEventWriter($this->graph);
        $writer2 = new DelegationEventWriter($graph2);

        $writer1->write('event.a', ['graph' => 1]);
        $writer1->write('event.b', ['graph' => 1]);
        $writer2->write('event.c', ['graph' => 2]);

        $graph1Events = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->get();
        $graph2Events = DelegationEvent::query()
            ->where('delegation_graph_id', $graph2->id)
            ->get();

        $this->assertSame(1, $graph1Events->firstWhere('event_type', 'event.a')->sequence);
        $this->assertSame(2, $graph1Events->firstWhere('event_type', 'event.b')->sequence);
        $this->assertSame(1, $graph2Events->first()->sequence);
    }

    public function test_write_returns_created_event(): void
    {
        $writer = new DelegationEventWriter($this->graph);

        $event = $writer->write('test.event', ['key' => 'value']);

        $this->assertInstanceOf(DelegationEvent::class, $event);
        $this->assertSame('test.event', $event->event_type);
        $this->assertSame(1, $event->sequence);
    }

    public function test_multiple_writers_for_same_graph_maintain_sequence(): void
    {
        $writer1 = new DelegationEventWriter($this->graph);
        $writer1->write('event.one', []);

        // Create a new writer instance (simulates different request/job)
        $writer2 = new DelegationEventWriter($this->graph);
        $writer2->write('event.two', []);

        $events = DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->orderBy('sequence')
            ->get();

        $this->assertSame(1, $events[0]->sequence);
        $this->assertSame(2, $events[1]->sequence);
    }
}
