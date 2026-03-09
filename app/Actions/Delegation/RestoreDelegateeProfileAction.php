<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegateeProfile;

class RestoreDelegateeProfileAction
{
    public function execute(DelegateeProfile $profile): bool
    {
        if ($profile->trashed()) {
            return (bool) $profile->restore();
        }

        return false;
    }
}
