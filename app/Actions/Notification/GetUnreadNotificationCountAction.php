<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;

class GetUnreadNotificationCountAction
{
    public function execute(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
