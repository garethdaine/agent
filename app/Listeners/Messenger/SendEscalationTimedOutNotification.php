<?php

declare(strict_types=1);

namespace App\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\Org\OrgRitualEscalationTimedOut;
use App\Services\Messenger\SystemNotificationDispatcher;

/**
 * Sends a messenger notification when a ritual escalation times out.
 *
 * Escalation timeouts are always treated as high-severity (error).
 */
class SendEscalationTimedOutNotification
{
    public function __construct(
        private readonly SystemNotificationDispatcher $dispatcher,
    ) {}

    public function handle(OrgRitualEscalationTimedOut $event): void
    {
        $escalation = $event->escalation;
        $run = $escalation->ritualRun;

        if ($run === null) {
            return;
        }

        $body = "**Escalation Type:** {$escalation->escalation_type}\n"
            ."**Ritual Run:** `{$run->correlation_id}`\n"
            ."**Timed Out At:** {$escalation->timeout_at?->toIso8601String()}";

        if ($escalation->escalatedToAgent) {
            $body .= "\n**Escalated To:** {$escalation->escalatedToAgent->name}";
        }

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'ritual.escalation_timed_out',
            userId: $run->user_id,
            severity: SystemNotificationPayload::SEVERITY_ERROR,
            title: "Ritual escalation timed out ({$escalation->escalation_type})",
            body: $body,
            context: [
                'escalation_id' => $escalation->id,
                'ritual_run_id' => $run->id,
                'escalation_type' => $escalation->escalation_type,
            ],
        ));
    }
}
