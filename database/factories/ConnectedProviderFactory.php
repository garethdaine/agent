<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConnectedProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConnectedProvider>
 */
class ConnectedProviderFactory extends Factory
{
    protected $model = ConnectedProvider::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'providerable_type' => 'App\\Models\\User',
            'providerable_id' => 1,
            'category' => $this->faker->randomElement(['communication', 'storage', 'development']),
            'driver' => $this->faker->randomElement(['slack', 'github', 'google']),
            'provider_user_id' => (string) $this->faker->unique()->randomNumber(8),
            'provider_workspace_id' => null,
            'provider_workspace_name' => null,
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_type' => 'Bearer',
            'expires_at' => now()->addDays(30),
            'scopes_json' => ['read', 'write'],
            'metadata_json' => null,
        ];
    }
}
