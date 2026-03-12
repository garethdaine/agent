<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CredentialUsageLog;
use App\Models\CredentialVault;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CredentialUsageLog>
 */
class CredentialUsageLogFactory extends Factory
{
    protected $model = CredentialUsageLog::class;

    public function definition(): array
    {
        return [
            'credential_id' => CredentialVault::factory(),
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement([
                CredentialUsageLog::ACTION_RETRIEVED,
                CredentialUsageLog::ACTION_CREATED,
                CredentialUsageLog::ACTION_UPDATED,
                CredentialUsageLog::ACTION_DELETED,
                CredentialUsageLog::ACTION_APPROVAL_CHECKED,
            ]),
            'context' => [],
            'outcome' => $this->faker->randomElement(['allowed', 'denied', 'needs_confirmation']),
        ];
    }
}
