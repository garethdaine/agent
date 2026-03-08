<?php

declare(strict_types=1);

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
        $runnerType = fake()->randomElement(['claude', 'codex', 'custom']);
        $commandTemplate = match ($runnerType) {
            'claude' => '{{runner}} -p {{task_markdown_path}}',
            'codex' => '{{runner}} exec {{task_markdown_path}}',
            default => '{{runner}} {{task_markdown_path}}',
        };

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'runner_type' => $runnerType,
            'command_template' => $commandTemplate,
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
