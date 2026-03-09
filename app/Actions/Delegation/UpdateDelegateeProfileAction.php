<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegateeProfile;

class UpdateDelegateeProfileAction
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  int[]|null  $capabilityIds
     */
    public function execute(DelegateeProfile $profile, array $attributes, ?array $capabilityIds = null, ?array $soul = null): DelegateeProfile
    {
        $profile->fill($attributes);
        $profile->save();

        if ($capabilityIds !== null) {
            $profile->capabilities()->sync($capabilityIds);
        }

        if ($soul !== null) {
            $profile->setSoul($soul);
        }

        return $profile;
    }
}
