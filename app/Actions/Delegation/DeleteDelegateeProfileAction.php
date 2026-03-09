<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegateeProfile;

class DeleteDelegateeProfileAction
{
    public function execute(DelegateeProfile $profile): bool
    {
        return (bool) $profile->delete();
    }
}
