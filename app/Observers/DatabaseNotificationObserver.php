<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\NotificationCreated;
use Illuminate\Notifications\DatabaseNotification;

class DatabaseNotificationObserver
{
    public function created(DatabaseNotification $notification): void
    {
        $notifiableId = $notification->notifiable_id;
        if (! $notifiableId) {
            return;
        }

        try {
            event(new NotificationCreated(
                userId: (int) $notifiableId,
                notificationId: (string) $notification->id,
                type: class_basename($notification->type),
                payload: $notification->data ?? [],
            ));
        } catch (\Throwable) {
            // Broadcasting should never break notification persistence
        }
    }
}
