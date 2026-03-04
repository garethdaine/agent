<?php

namespace App\Http\Middleware;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'delegationEnabled' => app(FeatureFlagManager::class)->enabled(FeatureFlagManager::DELEGATION_UI_ENABLED),
            'orgLayerEnabled' => app(FeatureFlagManager::class)->isEnabled(FeatureFlagManager::ORG_ENABLED)
                || config('agent.org.enabled', false),
            'operatorNavigation' => [
                'deployments' => '/agent/deployments',
                'systemOverview' => '/agent/system-overview',
            ],
            'notifications' => fn () => $this->notificationPayload($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationPayload(Request $request): array
    {
        $user = $request->user();

        if ($user === null || ! Schema::hasTable('notifications')) {
            return [
                'notifications' => [],
                'unread_count' => 0,
            ];
        }

        /** @var NotificationPresenter $presenter */
        $presenter = app(NotificationPresenter::class);

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $presenter->present($notification))
            ->values()
            ->all();

        return [
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }
}
