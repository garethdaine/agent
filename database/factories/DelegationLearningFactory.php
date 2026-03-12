<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegateeProfile;
use App\Models\DelegationGraph;
use App\Models\DelegationLearning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegationLearning>
 */
class DelegationLearningFactory extends Factory
{
    protected $model = DelegationLearning::class;

    public function definition(): array
    {
        return [
            'delegation_graph_id' => DelegationGraph::factory(),
            'delegatee_profile_id' => DelegateeProfile::factory(),
            'outcome_summary_json' => [
                'total_tasks' => $this->faker->numberBetween(1, 20),
                'succeeded' => $this->faker->numberBetween(0, 15),
                'failed' => $this->faker->numberBetween(0, 5),
                'cancelled' => 0,
                'other' => 0,
            ],
            'success_patterns_json' => [
                ['pattern' => 'code_execution', 'count' => $this->faker->numberBetween(1, 10)],
            ],
            'failure_patterns_json' => [],
            'aggregated_at' => now(),
        ];
    }
}
