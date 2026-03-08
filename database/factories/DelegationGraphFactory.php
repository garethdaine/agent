<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegationGraph;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationGraph>
 */
class DelegationGraphFactory extends Factory
{
    protected $model = DelegationGraph::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => DelegationGraph::STATUS_DRAFT,
            'cancellation_policy' => 'drain',
            'max_parallel_tasks' => 5,
            'metadata_json' => null,
            'error_code' => null,
            'error_summary' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationGraph::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationGraph::STATUS_SUCCEEDED,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationGraph::STATUS_FAILED,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'error_code' => 'TASK_FAILED',
            'error_summary' => 'One or more tasks failed to complete.',
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationGraph::STATUS_READY,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DelegationGraph::STATUS_CANCELLED,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);
    }
}
