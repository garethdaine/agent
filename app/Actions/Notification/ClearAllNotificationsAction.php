<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;

class ClearAllNotificationsAction
{
    public function execute(User $user): void
    {
        $user->notifications()->delete();
    }
}
