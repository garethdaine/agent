<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegationContext;
use App\Models\DelegationTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationContext>
 */
class DelegationContextFactory extends Factory
{
    protected $model = DelegationContext::class;

    public function definition(): array
    {
        return [
            'delegation_task_id' => DelegationTask::factory(),
            'context_json' => ['key' => $this->faker->word()],
            'propagation_direction' => 'down',
            'ttl_seconds' => 3600,
            'created_by_agent_id' => null,
        ];
    }
}
