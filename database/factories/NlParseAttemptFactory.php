<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NlParseAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NlParseAttempt>
 */
class NlParseAttemptFactory extends Factory
{
    protected $model = NlParseAttempt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'input_text' => $this->faker->sentence(),
            'timezone' => 'UTC',
            'parser_path' => $this->faker->randomElement(['cron', 'active_hours', 'schedule']),
            'confidence' => $this->faker->randomFloat(2, 0.5, 1.0),
            'cron_result' => '0 9 * * 1-5',
            'active_hours_result' => null,
            'status' => 'completed',
            'user_confirmed' => false,
            'error_message' => null,
            'completed_at' => now(),
        ];
    }
}
