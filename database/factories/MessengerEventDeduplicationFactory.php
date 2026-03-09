<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConnectorAccount;
use App\Models\MessengerEventDeduplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessengerEventDeduplication>
 */
class MessengerEventDeduplicationFactory extends Factory
{
    protected $model = MessengerEventDeduplication::class;

    public function definition(): array
    {
        return [
            'connector_account_id' => ConnectorAccount::factory(),
            'event_id' => $this->faker->unique()->uuid(),
            'expires_at' => now()->addHours(24),
        ];
    }
}
