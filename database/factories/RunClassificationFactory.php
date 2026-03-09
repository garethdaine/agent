<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RunClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunClassification>
 */
class RunClassificationFactory extends Factory
{
    protected $model = RunClassification::class;

    public function definition(): array
    {
        return [
            'run_id' => $this->faker->numberBetween(1, 10000),
            'workflow_key' => $this->faker->slug(2).'.v1',
            'classification' => $this->faker->randomElement(['success', 'failure', 'partial', 'timeout']),
            'weight' => $this->faker->randomFloat(2, 0.0, 1.0),
            'hard_fail' => false,
            'classification_reason_code' => null,
            'failure_class' => null,
            'failure_reason_code' => null,
            'verification_completed_at' => null,
            'assisted_sla_expires_at' => null,
            'classified_at' => now(),
            'reclassified_at' => null,
            'metadata_json' => null,
            'projection_build_id' => null,
        ];
    }
}
