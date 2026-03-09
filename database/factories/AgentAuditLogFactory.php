<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentAuditLog>
 */
class AgentAuditLogFactory extends Factory
{
    protected $model = AgentAuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_type' => 'user',
            'actor_id' => (string) $this->faker->randomNumber(5),
            'action' => $this->faker->randomElement(['create', 'update', 'delete', 'login', 'logout']),
            'target_type' => $this->faker->randomElement(['AgentJob', 'AgentJobRun', 'ChatSession']),
            'target_id' => (string) $this->faker->randomNumber(5),
            'changed_fields_json' => null,
            'before_json' => null,
            'after_json' => null,
            'request_id' => $this->faker->uuid(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'hostname' => $this->faker->domainName(),
            'outcome' => 'success',
            'error_code' => null,
            'error_message' => null,
        ];
    }
}
