<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DelegateeCapabilityPivot;
use App\Models\DelegateeProfile;
use App\Models\DelegationCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DelegateeCapabilityPivot>
 */
class DelegateeCapabilityPivotFactory extends Factory
{
    protected $model = DelegateeCapabilityPivot::class;

    public function definition(): array
    {
        return [
            'delegatee_profile_id' => DelegateeProfile::factory(),
            'delegation_capability_id' => DelegationCapability::factory(),
        ];
    }
}
