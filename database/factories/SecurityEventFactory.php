<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Security\SecurityEventType;
use App\Models\Runtime\RuntimeSession;
use App\Models\Security\SecurityEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityEvent>
 */
class SecurityEventFactory extends Factory
{
    protected $model = SecurityEvent::class;

    public function definition(): array
    {
        return [
            'runtime_session_id' => RuntimeSession::factory(),
            'runtime_tool_call_id' => null,
            'event_type' => $this->faker->randomElement(SecurityEventType::cases()),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'details_json' => ['message' => $this->faker->sentence()],
            'created_at' => now(),
        ];
    }
}
