<?php

namespace Database\Factories;

use App\Models\AgentConnector;
use App\Models\AgentConnectorApproval;
use App\Models\AgentConnectorConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConnectorApproval>
 */
class AgentConnectorApprovalFactory extends Factory
{
    protected $model = AgentConnectorApproval::class;

    public function definition(): array
    {
        return [
            'connection_id' => AgentConnectorConnection::factory(),
            'connector_id' => AgentConnector::factory(),
            'action_name' => 'create_invoice',
            'type' => AgentConnectorApproval::TYPE_APPROVAL,
            'status' => AgentConnectorApproval::STATUS_PENDING,
            'requested_by_run_id' => null,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'resolved_by' => null,
            'resolved_at' => null,
            'resolved_via' => null,
            'resolution_payload' => [],
            'request_context' => [],
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => AgentConnectorApproval::STATUS_APPROVED,
            'resolved_at' => now(),
            'resolved_via' => 'dashboard',
        ]);
    }

    public function clarification(): static
    {
        return $this->state(fn () => [
            'type' => AgentConnectorApproval::TYPE_CLARIFICATION,
        ]);
    }
}
