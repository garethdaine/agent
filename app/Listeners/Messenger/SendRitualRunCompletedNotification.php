<?php

declare(strict_types=1);

namespace App\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\DelegationGraphCompleted;
use App\Models\DelegationGraph;
use App\Models\OrgRitualRun;
use App\Services\Messenger\SystemNotificationDispatcher;

/**
 * Sends a messenger notification when a ritual run completes.
 *
 * Only fires for delegation graphs linked to an OrgRitualRun.
 * Maps final status to severity: succeeded→info, failed→error, partial/cancelled→warning.
 */
class SendRitualRunCompletedNotification
{
    public function __construct(
        private readonly SystemNotificationDispatcher $dispatcher,
    ) {}

    public function handle(DelegationGraphCompleted $event): void
    {
        $run = OrgRitualRun::with('template')
            ->where('delegation_graph_id', $event->graph->id)
            ->first();

        if ($run === null) {
            return;
        }

        $severity = match ($event->finalStatus) {
            DelegationGraph::STATUS_SUCCEEDED => SystemNotificationPayload::SEVERITY_INFO,
            DelegationGraph::STATUS_FAILED => SystemNotificationPayload::SEVERITY_ERROR,
            default => SystemNotificationPayload::SEVERITY_WARNING,
        };

        // Respect template's notification_level if more restrictive
        $templateLevel = $run->template?->notification_level;
        if ($templateLevel !== null && ! $this->dispatcher->shouldNotify($templateLevel, $severity)) {
            return;
        }

        $templateName = $run->template?->name ?? 'Unknown Ritual';
        $duration = $run->started_at && $run->completed_at
            ? $run->started_at->diffForHumans($run->completed_at, true)
            : 'N/A';

        $phaseCount = count($run->template?->phase_graph ?? []);
        $completedPhases = count(array_filter($run->phase_outputs ?? []));

        $body = "**Ritual:** {$templateName}\n"
            ."**Status:** {$event->finalStatus}\n"
            ."**Phases:** {$completedPhases}/{$phaseCount} completed\n"
            ."**Duration:** {$duration}\n"
            ."**Correlation ID:** `{$run->correlation_id}`";

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'ritual.completed',
            userId: $run->user_id,
            severity: $severity,
            title: "Ritual \"{$templateName}\" {$event->finalStatus}",
            body: $body,
            context: [
                'ritual_template_id' => $run->ritual_template_id,
                'ritual_run_id' => $run->id,
                'delegation_graph_id' => $event->graph->id,
                'final_status' => $event->finalStatus,
            ],
        ));
    }
}
