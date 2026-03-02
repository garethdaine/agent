<?php

namespace Database\Factories;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\MemoryConversationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryConversationLog>
 */
class MemoryConversationLogFactory extends Factory
{
    protected $model = MemoryConversationLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'run_id' => AgentJobRun::factory(),
            'job_id' => AgentJob::factory(),
            'role' => $this->faker->randomElement(MemoryConversationLog::$validRoles),
            'content' => $this->faker->paragraph(),
            'sequence' => $this->faker->numberBetween(1, 100),
            'event_type' => $this->faker->randomElement(MemoryConversationLog::$validEventTypes),
            'classification' => 'internal',
            'created_at' => now(),
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => MemoryConversationLog::ROLE_ASSISTANT,
        ]);
    }

    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => MemoryConversationLog::ROLE_USER,
        ]);
    }

    public function toolCall(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => MemoryConversationLog::EVENT_TOOL_CALL,
        ]);
    }
}
