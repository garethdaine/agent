<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConnectorConnection>
 */
class AgentConnectorConnectionFactory extends Factory
{
    protected $model = AgentConnectorConnection::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'connector_id' => AgentConnector::factory(),
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'health_score' => 1.00,
            'config' => [],
            'webhook_subscriptions' => [],
            'last_health_check_at' => null,
            'last_action_at' => null,
            'action_count_24h' => 0,
            'error_count_24h' => 0,
            'connected_by' => null,
            'connected_at' => now(),
            'disconnected_by' => null,
            'disconnected_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => AgentConnectorConnection::STATUS_PENDING]);
    }

    public function disconnected(): static
    {
        return $this->state(fn () => [
            'status' => AgentConnectorConnection::STATUS_DISCONNECTED,
            'disconnected_at' => now(),
        ]);
    }

    public function degraded(): static
    {
        return $this->state(fn () => [
            'status' => AgentConnectorConnection::STATUS_DEGRADED,
            'health_score' => 0.6,
        ]);
    }
}
