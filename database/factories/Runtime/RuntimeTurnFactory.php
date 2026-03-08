<?php

declare(strict_types=1);

namespace Database\Factories\Runtime;

use App\Enums\Runtime\RuntimeTurnStatus;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuntimeTurn>
 */
class RuntimeTurnFactory extends Factory
{
    protected $model = RuntimeTurn::class;

    public function definition(): array
    {
        return [
            'runtime_session_id' => RuntimeSession::factory(),
            'sequence' => $this->faker->numberBetween(1, 100),
            'status' => RuntimeTurnStatus::Pending,
            'summary' => $this->faker->optional()->sentence(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuntimeTurnStatus::Running,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuntimeTurnStatus::Completed,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RuntimeTurnStatus::Failed,
        ]);
    }
}
