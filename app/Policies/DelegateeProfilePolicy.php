<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DelegateeProfile;
use App\Models\User;

class DelegateeProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function view(User $user, DelegateeProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function update(User $user, DelegateeProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function delete(User $user, DelegateeProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function restore(User $user, DelegateeProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function forceDelete(User $user, DelegateeProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }
}
