<?php

namespace Database\Factories;

use App\Models\AgentConnector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConnector>
 */
class AgentConnectorFactory extends Factory
{
    protected $model = AgentConnector::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'display_name' => fake()->company() . ' Connector',
            'description' => fake()->sentence(10),
            'category' => fake()->randomElement(['crm', 'accounting', 'hr', 'marketing', 'communications']),
            'industries' => [],
            'version' => '1.0.0',
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => ['scopes' => ['read'], 'pkce' => true],
            'base_url' => 'https://api.example.com',
            'rate_limits' => ['requests_per_minute' => 60],
            'cost_model' => 'free',
            'risk_level' => 'standard',
            'actions' => [],
            'webhooks' => [],
            'icon_path' => null,
            'mcp_tool_prefix' => fake()->unique()->slug(1),
            'status' => AgentConnector::STATUS_AVAILABLE,
        ];
    }

    public function deprecated(): static
    {
        return $this->state(fn () => ['status' => AgentConnector::STATUS_DEPRECATED]);
    }
}
