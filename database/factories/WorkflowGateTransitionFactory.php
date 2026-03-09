<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkflowGateTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowGateTransition>
 */
class WorkflowGateTransitionFactory extends Factory
{
    protected $model = WorkflowGateTransition::class;

    public function definition(): array
    {
        return [
            'workflow_key' => $this->faker->slug(2).'.v1',
            'previous_gate_state' => $this->faker->randomElement(['open', 'closed', null]),
            'new_gate_state' => $this->faker->randomElement(['open', 'closed', 'throttled']),
            'source' => $this->faker->randomElement(['system', 'manual', 'escalation']),
            'reason_code' => null,
            'reason' => $this->faker->sentence(),
            'actor_id' => null,
            'run_id' => null,
            'projection_build_id' => null,
            'metadata_json' => null,
            'transitioned_at' => now(),
        ];
    }
}
