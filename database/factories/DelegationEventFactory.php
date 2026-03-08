<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegationEvent;
use App\Models\DelegationGraph;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationEvent>
 */
class DelegationEventFactory extends Factory
{
    protected $model = DelegationEvent::class;

    public function definition(): array
    {
        return [
            'delegation_graph_id' => DelegationGraph::factory(),
            'delegation_task_id' => null,
            'event_type' => 'graph_status_changed',
            'sequence' => 1,
            'payload_json' => [
                'from_status' => 'draft',
                'to_status' => 'running',
            ],
            'event_ts' => now(),
        ];
    }

    public function forTask(int $taskId): static
    {
        return $this->state(fn (array $attributes) => [
            'delegation_task_id' => $taskId,
            'event_type' => 'task_status_changed',
        ]);
    }

    public function withSequence(int $sequence): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence' => $sequence,
        ]);
    }

    public function withEventType(string $eventType): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $eventType,
        ]);
    }

    public function withPayload(array $payload): static
    {
        return $this->state(fn (array $attributes) => [
            'payload_json' => $payload,
        ]);
    }
}
