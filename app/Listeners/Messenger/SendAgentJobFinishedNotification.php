<?php

declare(strict_types=1);

namespace App\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\AgentJobRunFinished;
use App\Services\Messenger\SystemNotificationDispatcher;

/**
 * Sends a messenger notification when an agent job run fails.
 *
 * Only fires for terminal failure statuses: failed, killed, timed_out.
 * Succeeded runs are intentionally silent to avoid notification fatigue.
 */
class SendAgentJobFinishedNotification
{
    private const FAILURE_STATUSES = ['failed', 'killed', 'timed_out'];

    public function __construct(
        private readonly SystemNotificationDispatcher $dispatcher,
    ) {}

    public function handle(AgentJobRunFinished $event): void
    {
        if (! in_array($event->status, self::FAILURE_STATUSES, true)) {
            return;
        }

        $run = $event->run;
        $job = $run->job;

        if ($job === null) {
            return;
        }

        $duration = $run->duration_ms !== null // @phpstan-ignore notIdentical.alwaysTrue
            ? round($run->duration_ms / 1000, 1).'s'
            : 'N/A';

        $body = "**Job:** {$job->name}\n"
            ."**Status:** {$event->status}\n"
            ."**Duration:** {$duration}";

        if ($run->error_summary) {
            $body .= "\n**Error:** {$run->error_summary}";
        }

        if ($run->error_code) {
            $body .= "\n**Error Code:** `{$run->error_code}`";
        }

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'agent.job_failed',
            userId: $job->user_id,
            severity: SystemNotificationPayload::SEVERITY_ERROR,
            title: "Agent job \"{$job->name}\" {$event->status}",
            body: $body,
            context: [
                'agent_job_id' => $job->id,
                'agent_job_run_id' => $run->id,
                'status' => $event->status,
            ],
        ));
    }
}
