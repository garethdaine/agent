<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class FindUserNotificationAction
{
    public function execute(User $user, string $id): ?DatabaseNotification
    {
        return $user->notifications()
            ->whereKey($id)
            ->first();
    }
}
