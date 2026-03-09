<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SchedulerHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchedulerHeartbeat>
 */
class SchedulerHeartbeatFactory extends Factory
{
    protected $model = SchedulerHeartbeat::class;

    public function definition(): array
    {
        return [
            'source' => $this->faker->randomElement(['scheduler', 'horizon', 'cron']),
            'last_seen_at' => now(),
            'meta_json' => null,
        ];
    }
}
