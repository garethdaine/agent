<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationPresenter $presenter): JsonResponse
    {
        if (! $this->notificationsTableExists()) {
            return response()->json([
                'data' => [
                    'notifications' => [],
                    'unread_count' => 0,
                ],
            ]);
        }

        $user = $request->user();
        $limit = max(1, min((int) $request->integer('limit', 20), 50));

        $notifications = $user->notifications()
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => [
                'notifications' => $notifications
                    ->map(fn (DatabaseNotification $notification): array => $presenter->present($notification))
                    ->values()
                    ->all(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(string $id, Request $request): JsonResponse
    {
        if (! $this->notificationsTableExists()) {
            return ErrorEnvelope::make('NOT_FOUND', 'Notification not found.', 404);
        }

        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->first();

        if ($notification === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Notification not found.', 404);
        }

        $notification->markAsRead();

        return response()->json([
            'data' => [
                'id' => (string) $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        if (! $this->notificationsTableExists()) {
            return response()->json([
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
        }

        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        if (! $this->notificationsTableExists()) {
            return response()->json([
                'data' => [
                    'notifications' => [],
                    'unread_count' => 0,
                ],
            ]);
        }

        $request->user()->notifications()->delete();

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
