<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegationCoordinationLock;
use App\Models\DelegationTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationCoordinationLock>
 */
class DelegationCoordinationLockFactory extends Factory
{
    protected $model = DelegationCoordinationLock::class;

    public function definition(): array
    {
        return [
            'resource_type' => 'file',
            'resource_id' => '/app/'.fake()->word().'.php',
            'holder_delegation_task_id' => DelegationTask::factory(),
            'acquired_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'lock_type' => 'exclusive',
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (array $attributes) => [
            'lock_type' => 'shared',
        ]);
    }
}
