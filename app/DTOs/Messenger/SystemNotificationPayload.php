<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

/**
 * Payload for system event notifications dispatched to messenger channels.
 *
 * Used by SystemNotificationDispatcher to route formatted messages
 * to eligible ConnectorAccounts based on notification_level and event_types.
 */
final readonly class SystemNotificationPayload
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_ERROR = 'error';

    /**
     * @param  string  $type  Event type key (e.g. 'ritual.completed', 'delegation.task_failed')
     * @param  int  $userId  Owner user ID for routing to their linked messenger accounts
     * @param  string  $severity  One of: info, warning, error
     * @param  string  $title  Short summary line (displayed in bold)
     * @param  string  $body  Markdown content with event details
     * @param  array<string, mixed>  $context  Extra metadata (ritual_template_id, run_id, etc.)
     */
    public function __construct(
        public string $type,
        public int $userId,
        public string $severity,
        public string $title,
        public string $body,
        public array $context = [],
    ) {}
}
