<?php

namespace App\Support\Delegation;

use App\Models\DelegationEvent;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use Carbon\CarbonImmutable;

/**
 * Writes delegation events with auto-incrementing sequence per graph.
 *
 * Mirrors the RunEventWriter pattern from the agent execution subsystem.
 * Each event is assigned a monotonically increasing sequence number
 * within its graph scope, enabling ordered event replay and reliable
 * frontend synchronization.
 */
class DelegationEventWriter
{
    private int $nextSequence;

    public function __construct(private readonly DelegationGraph $graph)
    {
        $this->nextSequence = (int) (DelegationEvent::query()
            ->where('delegation_graph_id', $this->graph->id)
            ->max('sequence') ?? 0) + 1;
    }

    /**
     * Write an event to the delegation event log.
     *
     * @param  string  $eventType  The type of event (e.g., 'graph.started', 'task.completed')
     * @param  array<string, mixed>  $payload  The event payload data
     * @param  DelegationTask|null  $task  Optional task to associate with the event
     * @return DelegationEvent The created event record
     */
    public function write(string $eventType, array $payload, ?DelegationTask $task = null): DelegationEvent
    {
        return DelegationEvent::query()->create([
            'delegation_graph_id' => $this->graph->id,
            'delegation_task_id' => $task?->id,
            'event_type' => $eventType,
            'sequence' => $this->nextSequence++,
            'payload_json' => $payload,
            'event_ts' => CarbonImmutable::now('UTC'),
        ]);
    }
}
