<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AccountLinkToken;
use App\Models\ConnectorAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountLinkToken>
 */
class AccountLinkTokenFactory extends Factory
{
    protected $model = AccountLinkToken::class;

    public function definition(): array
    {
        return [
            'token_hash' => AccountLinkToken::hashToken($this->faker->uuid()),
            'connector_account_id' => ConnectorAccount::factory(),
            'provider_user_id' => (string) $this->faker->unique()->randomNumber(8),
            'issued_at' => now(),
            'expires_at' => now()->addHours(24),
            'consumed_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'consumed_at' => now(),
        ]);
    }
}
