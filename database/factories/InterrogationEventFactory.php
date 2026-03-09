<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterrogationEvent>
 */
class InterrogationEventFactory extends Factory
{
    protected $model = InterrogationEvent::class;

    public function definition(): array
    {
        return [
            'interrogation_session_id' => InterrogationSession::factory(),
            'event_type' => $this->faker->randomElement(['started', 'completed', 'failed', 'status_change']),
            'sequence' => $this->faker->numberBetween(1, 100),
            'payload' => json_encode(['message' => $this->faker->sentence()]),
            'event_ts' => now(),
        ];
    }
}
