<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChatAction;
use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\PendingConfirmation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PendingConfirmation>
 */
class PendingConfirmationFactory extends Factory
{
    protected $model = PendingConfirmation::class;

    public function definition(): array
    {
        return [
            'chat_action_id' => ChatAction::factory(),
            'chat_session_id' => ChatSession::factory(),
            'connector_account_id' => ConnectorAccount::factory(),
            'confirmation_token' => Str::random(64),
            'provider_message_id' => null,
            'callback_data' => null,
            'expires_at' => now()->addMinutes(30),
            'confirmed_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled_at' => now(),
        ]);
    }
}
