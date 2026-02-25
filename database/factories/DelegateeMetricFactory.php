<?php

namespace Database\Factories;

use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegateeMetric>
 */
class DelegateeMetricFactory extends Factory
{
    protected $model = DelegateeMetric::class;

    public function definition(): array
    {
        return [
            'delegatee_profile_id' => DelegateeProfile::factory(),
            'window_24h_json' => null,
            'window_7d_json' => null,
            'last_recomputed_at' => null,
        ];
    }

    public function withMetrics(): static
    {
        return $this->state(fn (array $attributes) => [
            'window_24h_json' => [
                'total_attempts' => fake()->numberBetween(1, 100),
                'successful_attempts' => fake()->numberBetween(0, 50),
                'success_rate' => fake()->randomFloat(2, 0, 1),
                'avg_duration_ms' => fake()->numberBetween(1000, 60000),
            ],
            'window_7d_json' => [
                'total_attempts' => fake()->numberBetween(10, 500),
                'successful_attempts' => fake()->numberBetween(5, 250),
                'success_rate' => fake()->randomFloat(2, 0, 1),
                'avg_duration_ms' => fake()->numberBetween(1000, 60000),
            ],
            'last_recomputed_at' => now(),
        ]);
    }
}
