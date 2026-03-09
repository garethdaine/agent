<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EscalationIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EscalationIncident>
 */
class EscalationIncidentFactory extends Factory
{
    protected $model = EscalationIncident::class;

    public function definition(): array
    {
        return [
            'workflow_key' => $this->faker->slug(2).'.v1',
            'trigger_type' => $this->faker->randomElement(['failure_rate', 'timeout', 'manual']),
            'status' => 'open',
            'reason_code' => null,
            'reason' => $this->faker->sentence(),
            'opened_at' => now(),
            'investigating_at' => null,
            'resolved_at' => null,
            'last_triggered_at' => now(),
            'projection_build_id' => null,
            'metadata_json' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}
