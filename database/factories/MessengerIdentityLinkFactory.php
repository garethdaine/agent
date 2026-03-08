<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MessengerIdentityLink>
 */
class MessengerIdentityLinkFactory extends Factory
{
    protected $model = MessengerIdentityLink::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'user_id' => User::factory(),
            'connector_account_id' => ConnectorAccount::factory(),
            'provider_user_id' => 'U'.strtoupper(Str::random(10)),
            'provider_username' => $this->faker->userName(),
            'expires_at' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forConnector(ConnectorAccount $account): static
    {
        return $this->state(fn (array $attributes) => [
            'connector_account_id' => $account->id,
        ]);
    }

    public function expiring(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function permanent(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }
}
