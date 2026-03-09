<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use Illuminate\Notifications\DatabaseNotification;

class MarkNotificationReadAction
{
    public function execute(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }
}
