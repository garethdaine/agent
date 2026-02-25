<?php

namespace Database\Factories;

use App\Models\DelegateeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegateeProfile>
 */
class DelegateeProfileFactory extends Factory
{
    protected $model = DelegateeProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'runner_type' => fake()->randomElement(['claude', 'codex', 'cursor']),
            'command_template' => '/usr/bin/env {{runner}} --config {{config}}',
            'working_directory' => '/tmp/delegatee',
            'env_json' => null,
            'config_json' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withEnv(array $env): static
    {
        return $this->state(fn (array $attributes) => [
            'env_json' => $env,
        ]);
    }

    public function withConfig(array $config): static
    {
        return $this->state(fn (array $attributes) => [
            'config_json' => $config,
        ]);
    }
}
