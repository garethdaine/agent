<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrgAgentProfile;
use App\Models\User;

class OrgAgentProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function view(User $user, OrgAgentProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function update(User $user, OrgAgentProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function delete(User $user, OrgAgentProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function restore(User $user, OrgAgentProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function forceDelete(User $user, OrgAgentProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }
}
