<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notification\ClearAllNotificationsAction;
use App\Actions\Notification\FindUserNotificationAction;
use App\Actions\Notification\GetUnreadNotificationCountAction;
use App\Actions\Notification\ListUserNotificationsAction;
use App\Actions\Notification\MarkAllNotificationsReadAction;
use App\Actions\Notification\MarkNotificationReadAction;
use App\Http\Controllers\Controller;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        NotificationPresenter $presenter,
        ListUserNotificationsAction $listNotifications,
        GetUnreadNotificationCountAction $unreadCount,
    ): JsonResponse {
        if (! $this->notificationsTableExists()) {
            return response()->json([
                'data' => [
                    'notifications' => [],
                    'unread_count' => 0,
                ],
            ]);
        }

        $user = $request->user();
        $limit = (int) $request->integer('limit', 20);

        $notifications = $listNotifications->execute($user, $limit);

        return response()->json([
            'data' => [
                'notifications' => $notifications
                    ->map(fn (DatabaseNotification $notification): array => $presenter->present($notification))
                    ->values()
                    ->all(),
                'unread_count' => $unreadCount->execute($user),
            ],
        ]);
    }

    public function markAsRead(
        string $id,
        Request $request,
        FindUserNotificationAction $findNotification,
        MarkNotificationReadAction $markRead,
        GetUnreadNotificationCountAction $unreadCount,
    ): JsonResponse {
        if (! $this->notificationsTableExists()) {
            return ErrorEnvelope::make('NOT_FOUND', 'Notification not found.', 404);
        }

        $notification = $findNotification->execute($request->user(), $id);

        if ($notification === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Notification not found.', 404);
        }

        $markRead->execute($notification);

        return response()->json([
            'data' => [
                'id' => (string) $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
                'unread_count' => $unreadCount->execute($request->user()),
            ],
        ]);
    }

    public function markAllAsRead(Request $request, MarkAllNotificationsReadAction $markAllRead): JsonResponse
    {
        if (! $this->notificationsTableExists()) {
            return response()->json([
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
        }

        $markAllRead->execute($request->user());

        return response()->json([
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }

    public function clearAll(Request $request, ClearAllNotificationsAction $clearAll): JsonResponse
    {
        if (! $this->notificationsTableExists()) {
            return response()->json([
                'data' => [
                    'notifications' => [],
                    'unread_count' => 0,
                ],
            ]);
        }

        $clearAll->execute($request->user());

        return response()->json([
            'data' => [
                'notifications' => [],
                'unread_count' => 0,
            ],
        ]);
    }

    private function notificationsTableExists(): bool
    {
        return Schema::hasTable('notifications');
    }
}
