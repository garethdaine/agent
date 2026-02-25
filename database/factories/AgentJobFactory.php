<?php

namespace Database\Factories;

use App\Models\AgentJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentJob>
 */
class AgentJobFactory extends Factory
{
    protected $model = AgentJob::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'cron_expression' => '0 * * * *',
            'timezone' => 'UTC',
            'is_enabled' => true,
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => '/usr/local/bin/claude --task {{task}}',
            'task_markdown_path' => '/tmp/task.md',
            'working_directory' => '/tmp',
            'env_json' => null,
            'last_validated_executable_path' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
