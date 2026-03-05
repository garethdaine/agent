<?php

namespace Database\Factories\Runtime;

use App\Enums\Runtime\RuntimeApprovalState;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeApproval>
 */
class RuntimeApprovalFactory extends Factory
{
    protected $model = RuntimeApproval::class;

    public function definition(): array
    {
        return [
            'runtime_tool_call_id' => RuntimeToolCall::factory()->requiresApproval(),
            'requested_by' => User::factory(),
            'state' => RuntimeApprovalState::Pending,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => RuntimeApprovalState::Approved,
            'decision_by' => User::factory(),
            'decision_reason' => $this->faker->optional()->sentence(),
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => RuntimeApprovalState::Denied,
            'decision_by' => User::factory(),
            'decision_reason' => $this->faker->sentence(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => RuntimeApprovalState::Expired,
            'expires_at' => now()->subHour(),
        ]);
    }
}
