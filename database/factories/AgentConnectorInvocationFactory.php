<?php

namespace Database\Factories;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorInvocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConnectorInvocation>
 */
class AgentConnectorInvocationFactory extends Factory
{
    protected $model = AgentConnectorInvocation::class;

    public function definition(): array
    {
        return [
            'connection_id' => AgentConnectorConnection::factory(),
            'connector_id' => AgentConnector::factory(),
            'action_name' => 'list_contacts',
            'run_attempt_id' => null,
            'delegatee_id' => null,
            'workflow_key' => null,
            'http_method' => 'GET',
            'http_status' => 200,
            'duration_ms' => fake()->numberBetween(50, 2000),
            'request_size_bytes' => fake()->numberBetween(100, 5000),
            'response_size_bytes' => fake()->numberBetween(500, 50000),
            'token_usage' => null,
            'retry_count' => 0,
            'outcome' => AgentConnectorInvocation::OUTCOME_SUCCESS,
            'error_message' => null,
            'created_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'outcome' => AgentConnectorInvocation::OUTCOME_FAILED,
            'http_status' => 500,
            'error_message' => 'Internal server error',
        ]);
    }
}
