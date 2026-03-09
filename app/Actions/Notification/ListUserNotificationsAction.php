<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

class ListUserNotificationsAction
{
    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function execute(User $user, int $limit = 20): Collection
    {
        return $user->notifications()
            ->latest()
            ->limit(max(1, min($limit, 50)))
            ->get();
    }
}
