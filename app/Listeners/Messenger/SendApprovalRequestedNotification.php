<?php

declare(strict_types=1);

namespace App\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\RuntimeApprovalRequested;
use App\Services\Messenger\SystemNotificationDispatcher;

/**
 * Sends a messenger notification when a runtime tool call requires approval.
 *
 * This ensures users are alerted even when not actively watching the UI.
 */
class SendApprovalRequestedNotification
{
    public function __construct(
        private readonly SystemNotificationDispatcher $dispatcher,
    ) {}

    public function handle(RuntimeApprovalRequested $event): void
    {
        $body = "**Tool:** `{$event->toolName}`\n"
            ."**Session:** #{$event->sessionId}\n"
            ."**Approval ID:** #{$event->approvalId}\n\n"
            .'Action required — approve or reject in the UI.';

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'runtime.approval_requested',
            userId: $event->userId,
            severity: SystemNotificationPayload::SEVERITY_WARNING,
            title: "Approval required for \"{$event->toolName}\"",
            body: $body,
            context: [
                'approval_id' => $event->approvalId,
                'tool_call_id' => $event->toolCallId,
                'tool_name' => $event->toolName,
                'session_id' => $event->sessionId,
            ],
        ));
    }
}
