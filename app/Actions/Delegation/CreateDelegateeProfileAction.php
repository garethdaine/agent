<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegateeProfile;
use App\Models\User;

class CreateDelegateeProfileAction
{
    /**
     * @param  array{name: string, runner_type: string, command_template: string, working_directory: string, env_json?: array|null, config_json?: array|null, is_active?: bool}  $attributes
     * @param  int[]|null  $capabilityIds
     */
    public function execute(User $user, array $attributes, ?array $capabilityIds = null, ?array $soul = null): DelegateeProfile
    {
        /** @var DelegateeProfile $profile */
        $profile = $user->delegateeProfiles()->create($attributes);

        if ($capabilityIds !== null) {
            $profile->capabilities()->sync($capabilityIds);
        }

        if ($soul !== null) {
            $profile->setSoul($soul);
        }

        return $profile;
    }
}
