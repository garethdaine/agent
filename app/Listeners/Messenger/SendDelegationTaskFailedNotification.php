<?php

declare(strict_types=1);

namespace App\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\DelegationTaskVerified;
use App\Services\Messenger\SystemNotificationDispatcher;

/**
 * Sends a messenger notification when a delegation task fails verification.
 *
 * Only fires when verification fails ($event->passed === false).
 */
class SendDelegationTaskFailedNotification
{
    public function __construct(
        private readonly SystemNotificationDispatcher $dispatcher,
    ) {}

    public function handle(DelegationTaskVerified $event): void
    {
        if ($event->passed) {
            return;
        }

        $task = $event->task;
        $graph = $task->graph;

        if ($graph === null) {
            return;
        }

        $failedStep = $event->failedStepOrder !== null
            ? "at step #{$event->failedStepOrder}"
            : '';

        $body = "**Task:** {$task->name}\n"
            ."**Graph:** `{$graph->id}`\n"
            ."**Attempt:** #{$event->attempt->attempt_number}\n"
            ."**Failed:** Verification failed {$failedStep}";

        if ($event->attempt->error_summary) {
            $body .= "\n**Error:** {$event->attempt->error_summary}";
        }

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'delegation.task_failed',
            userId: $graph->user_id,
            severity: SystemNotificationPayload::SEVERITY_WARNING,
            title: "Delegation task \"{$task->name}\" failed verification",
            body: $body,
            context: [
                'delegation_graph_id' => $graph->id,
                'delegation_task_id' => $task->id,
                'attempt_id' => $event->attempt->id,
                'failed_step_order' => $event->failedStepOrder,
            ],
        ));
    }
}
