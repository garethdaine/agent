<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConnectorAccount;
use App\Models\MessengerDeadLetter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessengerDeadLetter>
 */
class MessengerDeadLetterFactory extends Factory
{
    protected $model = MessengerDeadLetter::class;

    public function definition(): array
    {
        return [
            'connector_account_id' => ConnectorAccount::factory(),
            'original_payload' => ['type' => 'message', 'text' => $this->faker->sentence()],
            'error_message' => $this->faker->sentence(),
            'error_history' => [],
            'attempts' => $this->faker->numberBetween(1, 5),
            'failed_at' => now(),
            'retried_at' => null,
        ];
    }
}
