<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorCredentialEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConnectorCredentialEvent>
 */
class AgentConnectorCredentialEventFactory extends Factory
{
    protected $model = AgentConnectorCredentialEvent::class;

    public function definition(): array
    {
        return [
            'credential_id' => AgentConnectorCredential::factory(),
            'event_type' => $this->faker->randomElement(['created', 'rotated', 'revoked', 'accessed']),
            'actor_id' => User::factory(),
            'details' => ['reason' => $this->faker->sentence()],
            'created_at' => now(),
        ];
    }
}
