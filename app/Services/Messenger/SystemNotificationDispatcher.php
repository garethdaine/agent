<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Jobs\Messenger\SendOutboundMessage;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Routes system event notifications to eligible messenger channels.
 *
 * Finds ConnectorAccounts linked to the payload's user via MessengerIdentityLink,
 * filters by notification level and event type whitelist, then dispatches
 * SendOutboundMessage jobs for delivery.
 *
 * Notifications are best-effort — failures are logged but never thrown.
 */
class SystemNotificationDispatcher
{
    private const SEVERITY_EMOJI = [
        SystemNotificationPayload::SEVERITY_INFO => 'ℹ️',
        SystemNotificationPayload::SEVERITY_WARNING => '⚠️',
        SystemNotificationPayload::SEVERITY_ERROR => '🔴',
    ];

    /**
     * Notification levels ordered by verbosity (most restrictive first).
     * Maps each level to the severities it allows.
     *
     * @var array<string, array<string>>
     */
    private const LEVEL_SEVERITY_MAP = [
        'escalations_only' => [SystemNotificationPayload::SEVERITY_ERROR],
        'lifecycle' => [
            SystemNotificationPayload::SEVERITY_ERROR,
            SystemNotificationPayload::SEVERITY_WARNING,
            SystemNotificationPayload::SEVERITY_INFO,
        ],
        'verbose' => [
            SystemNotificationPayload::SEVERITY_ERROR,
            SystemNotificationPayload::SEVERITY_WARNING,
            SystemNotificationPayload::SEVERITY_INFO,
        ],
    ];

    public function __construct(
        private readonly FeatureFlagManager $featureFlagManager,
        private readonly ChatSessionManager $chatSessionManager,
    ) {}

    /**
     * Dispatch a system notification to all eligible messenger accounts for the user.
     */
    public function dispatch(SystemNotificationPayload $payload): void
    {
        try {
            if (! $this->featureFlagManager->isEnabled(FeatureFlagManager::MESSENGER_SYSTEM_NOTIFICATIONS_ENABLED)) {
                return;
            }

            $accounts = $this->findEligibleAccounts($payload);

            foreach ($accounts as $account) {
                $this->sendToAccount($account, $payload);
            }
        } catch (Throwable $e) {
            Log::warning('SystemNotificationDispatcher: Failed to dispatch notification', [
                'type' => $payload->type,
                'user_id' => $payload->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check whether a notification level allows the given severity.
     */
    public function shouldNotify(string $configLevel, string $severity): bool
    {
        $allowed = self::LEVEL_SEVERITY_MAP[$configLevel] ?? self::LEVEL_SEVERITY_MAP['lifecycle'];

        return in_array($severity, $allowed, true);
    }

    /**
     * Find all ConnectorAccounts eligible to receive this notification.
     *
     * @return \Illuminate\Support\Collection<int, ConnectorAccount>
     */
    private function findEligibleAccounts(SystemNotificationPayload $payload): \Illuminate\Support\Collection
    {
        $links = MessengerIdentityLink::query()
            ->forUser($payload->userId)
            ->valid()
            ->with('connectorAccount')
            ->get();

        return $links
            ->pluck('connectorAccount')
            ->filter()
            ->unique('id')
            ->filter(function (ConnectorAccount $account) use ($payload) {
                if (! $account->isConnected()) {
                    return false;
                }

                $settings = $account->getSystemNotifications();

                if (! $settings['enabled']) {
                    return false;
                }

                if ($settings['channel_id'] === null) {
                    return false;
                }

                // Check notification level allows this severity
                if (! $this->shouldNotify($settings['notification_level'], $payload->severity)) {
                    return false;
                }

                // Check event type whitelist (empty = all events allowed)
                if (! empty($settings['event_types']) && ! in_array($payload->type, $settings['event_types'], true)) {
                    return false;
                }

                return true;
            });
    }

    /**
     * Send the notification to a specific ConnectorAccount.
     */
    private function sendToAccount(ConnectorAccount $account, SystemNotificationPayload $payload): void
    {
        try {
            $user = User::find($payload->userId);
            if ($user === null) {
                return;
            }

            $settings = $account->getSystemNotifications();
            $channelId = $settings['channel_id'];
            $threadId = $settings['thread_id'] ?? null;

            $session = $this->chatSessionManager->findOrCreate(
                $account,
                $channelId,
                $threadId,
                $user
            );

            $content = $this->formatMessage($payload);

            SendOutboundMessage::dispatch(
                sessionId: $session->id,
                content: $content,
                threadId: $threadId,
            );
        } catch (Throwable $e) {
            Log::warning('SystemNotificationDispatcher: Failed to send to account', [
                'account_id' => $account->id,
                'provider' => $account->provider,
                'type' => $payload->type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format the notification payload into a markdown message.
     */
    private function formatMessage(SystemNotificationPayload $payload): string
    {
        $emoji = self::SEVERITY_EMOJI[$payload->severity] ?? 'ℹ️';

        return "{$emoji} **{$payload->title}**\n\n{$payload->body}";
    }
}
