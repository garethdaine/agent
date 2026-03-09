<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Security\AccountSecurityOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountSecurityOverride>
 */
class AccountSecurityOverrideFactory extends Factory
{
    protected $model = AccountSecurityOverride::class;

    public function definition(): array
    {
        return [
            'account_id' => $this->faker->uuid(),
            'config_key' => $this->faker->unique()->slug(2),
            'config_value' => $this->faker->word(),
            'created_by' => $this->faker->uuid(),
        ];
    }
}
