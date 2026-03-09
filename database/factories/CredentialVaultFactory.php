<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CredentialVault;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CredentialVault>
 */
class CredentialVaultFactory extends Factory
{
    protected $model = CredentialVault::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => $this->faker->randomElement(['openai', 'anthropic', 'github', 'slack']),
            'key' => $this->faker->unique()->slug(2),
            'encrypted_value' => $this->faker->sha256(),
            'metadata' => null,
        ];
    }
}
