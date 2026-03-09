<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;

class MarkAllNotificationsReadAction
{
    public function execute(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
