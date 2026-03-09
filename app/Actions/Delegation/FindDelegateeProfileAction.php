<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegateeProfile;
use App\Models\User;

class FindDelegateeProfileAction
{
    public function execute(User $user, int $id, bool $withTrashed = true): ?DelegateeProfile
    {
        $query = $user->delegateeProfiles();

        if ($withTrashed) {
            $query->withTrashed(); // @phpstan-ignore method.notFound
        }

        /** @var DelegateeProfile|null */
        return $query->find($id);
    }

    public function findById(int $id): ?DelegateeProfile
    {
        return DelegateeProfile::find($id);
    }
}
