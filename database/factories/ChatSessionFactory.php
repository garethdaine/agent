<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChatSession;
use App\Models\ConnectorAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatSession>
 */
class ChatSessionFactory extends Factory
{
    protected $model = ChatSession::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'user_id' => User::factory(),
            'connector_account_id' => ConnectorAccount::factory(),
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C'.strtoupper(Str::random(10)),
            'thread_id' => null,
            'status' => ChatSession::STATUS_ACTIVE,
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
            'provider' => $account->provider,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatSession::STATUS_ACTIVE,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChatSession::STATUS_ARCHIVED,
        ]);
    }

    public function withThread(): static
    {
        return $this->state(fn (array $attributes) => [
            'thread_id' => $this->faker->numerify('##########.######'),
        ]);
    }

    public function slack(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'channel_id' => 'C'.strtoupper(Str::random(10)),
        ]);
    }

    public function telegram(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'channel_id' => (string) $this->faker->numberBetween(100000000, 999999999),
        ]);
    }
}
