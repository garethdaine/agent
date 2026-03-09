<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConnectorWebhookEvent>
 */
class AgentConnectorWebhookEventFactory extends Factory
{
    protected $model = AgentConnectorWebhookEvent::class;

    public function definition(): array
    {
        return [
            'connection_id' => AgentConnectorConnection::factory(),
            'connector_id' => $this->faker->uuid(),
            'event_type' => $this->faker->randomElement(['message.created', 'issue.updated', 'push']),
            'external_event_id' => $this->faker->uuid(),
            'payload_hash' => hash('sha256', $this->faker->sentence()),
            'processing_status' => 'pending',
            'routed_to_workflow' => null,
            'routed_to_job_id' => null,
            'processing_duration_ms' => null,
            'retry_count' => 0,
            'error_message' => null,
            'created_at' => now(),
        ];
    }
}
