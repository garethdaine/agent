<?php

declare(strict_types=1);

namespace App\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\RepoAnalysisSessionUpdated;
use App\Models\RepoAnalysisSession;
use App\Services\Messenger\SystemNotificationDispatcher;

/**
 * Sends a messenger notification when a code analysis session reaches a terminal state.
 *
 * Only fires for terminal status values (completed, failed).
 */
class SendRepoAnalysisCompletedNotification
{
    private const TERMINAL_STATUSES = ['completed', 'failed'];

    public function __construct(
        private readonly SystemNotificationDispatcher $dispatcher,
    ) {}

    public function handle(RepoAnalysisSessionUpdated $event): void
    {
        $status = $event->payload['status'] ?? null;

        if ($status === null || ! in_array($status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $session = RepoAnalysisSession::find($event->sessionId);
        if ($session === null) {
            return;
        }

        $severity = $status === 'completed'
            ? SystemNotificationPayload::SEVERITY_INFO
            : SystemNotificationPayload::SEVERITY_ERROR;

        $body = "**Session:** #{$event->sessionId}\n"
            ."**Status:** {$status}";

        if (isset($event->payload['summary'])) {
            $body .= "\n\n{$event->payload['summary']}";
        }

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'code_analysis.completed',
            userId: $session->user_id,
            severity: $severity,
            title: "Code analysis {$status}",
            body: $body,
            context: [
                'repo_analysis_session_id' => $event->sessionId,
                'status' => $status,
            ],
        ));
    }
}
