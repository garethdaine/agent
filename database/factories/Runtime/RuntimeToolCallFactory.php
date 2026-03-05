<?php

namespace Database\Factories\Runtime;

use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\Runtime\RuntimeTurn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeToolCall>
 */
class RuntimeToolCallFactory extends Factory
{
    protected $model = RuntimeToolCall::class;

    public function definition(): array
    {
        return [
            'runtime_turn_id' => RuntimeTurn::factory(),
            'tool_name' => $this->faker->randomElement(['fs.read', 'fs.write', 'runtime.exec', 'web.search']),
            'arguments_json' => ['path' => '/tmp/'.$this->faker->word().'.txt'],
            'status' => RuntimeToolCallStatus::PendingApproval,
            'requires_approval' => false,
        ];
    }

    public function requiresApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_approval' => true,
            'status' => RuntimeToolCallStatus::PendingApproval,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuntimeToolCallStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuntimeToolCallStatus::Completed,
            'result_json' => ['success' => true],
            'duration_ms' => $this->faker->numberBetween(10, 5000),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuntimeToolCallStatus::Failed,
            'result_json' => ['error' => 'Operation failed'],
            'duration_ms' => $this->faker->numberBetween(10, 5000),
        ]);
    }
}
