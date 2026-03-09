<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentJobRun;
use App\Models\MemoryFormationFailure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryFormationFailure>
 */
class MemoryFormationFailureFactory extends Factory
{
    protected $model = MemoryFormationFailure::class;

    public function definition(): array
    {
        return [
            'run_id' => AgentJobRun::factory(),
            'user_id' => User::factory(),
            'failure_type' => $this->faker->randomElement([
                MemoryFormationFailure::TYPE_EXTRACTION,
                MemoryFormationFailure::TYPE_EMBEDDING,
                MemoryFormationFailure::TYPE_GRAPH,
                MemoryFormationFailure::TYPE_PROVIDER_ERROR,
                MemoryFormationFailure::TYPE_TIMEOUT,
            ]),
            'error_message' => $this->faker->sentence(),
            'attempts' => $this->faker->numberBetween(1, 5),
            'backfilled_at' => null,
            'payload_json' => null,
            'created_at' => now(),
        ];
    }
}
