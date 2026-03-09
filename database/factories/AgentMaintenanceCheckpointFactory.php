<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentMaintenanceCheckpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentMaintenanceCheckpoint>
 */
class AgentMaintenanceCheckpointFactory extends Factory
{
    protected $model = AgentMaintenanceCheckpoint::class;

    public function definition(): array
    {
        return [
            'domain' => $this->faker->randomElement(['memory', 'delegation', 'runs', 'events']),
            'status' => 'completed',
            'last_processed_id' => $this->faker->numberBetween(1, 10000),
            'processed_rows' => $this->faker->numberBetween(0, 5000),
            'progress_json' => null,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'finished_at' => null,
        ]);
    }
}
