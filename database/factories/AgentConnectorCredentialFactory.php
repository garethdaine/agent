<?php

namespace Database\Factories;

use App\Models\AgentConnector;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConnectorCredential>
 */
class AgentConnectorCredentialFactory extends Factory
{
    protected $model = AgentConnectorCredential::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'connector_id' => AgentConnector::factory(),
            'auth_type' => 'oauth2_authorization_code',
            'encrypted_data' => 'encrypted-placeholder',
            'encryption_key_id' => 'v1',
            'scopes_granted' => ['read'],
            'token_expires_at' => now()->addHour(),
            'refresh_token_expires_at' => now()->addDays(30),
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
            'last_refreshed_at' => null,
            'last_used_at' => null,
            'refresh_failure_count' => 0,
            'created_by' => null,
            'updated_by' => null,
            'revoked_by' => null,
            'revoked_at' => null,
            'rotation_count' => 0,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => AgentConnectorCredential::STATUS_EXPIRED,
            'token_expires_at' => now()->subHour(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => AgentConnectorCredential::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }
}
