<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrgRitualRun;
use App\Models\User;

class OrgRitualRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null; // @phpstan-ignore notIdentical.alwaysTrue
    }

    public function view(User $user, OrgRitualRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function retry(User $user, OrgRitualRun $run): bool
    {
        return $run->user_id === $user->id;
    }
}
