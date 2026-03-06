<?php

declare(strict_types=1);

namespace App\Listeners\Messenger;

use App\DTOs\Messenger\SystemNotificationPayload;
use App\Events\InterrogationPhaseChanged;
use App\Models\InterrogationSession;
use App\Services\Messenger\SystemNotificationDispatcher;

/**
 * Sends a messenger notification on significant interrogation phase changes.
 *
 * Only fires for terminal statuses (completed, failed) to avoid noise.
 */
class SendInterrogationPhaseNotification
{
    private const TERMINAL_STATUSES = ['completed', 'failed'];

    public function __construct(
        private readonly SystemNotificationDispatcher $dispatcher,
    ) {}

    public function handle(InterrogationPhaseChanged $event): void
    {
        if (! in_array($event->status, self::TERMINAL_STATUSES, true)) {
            return;
        }

        $session = InterrogationSession::find($event->sessionId);
        if ($session === null) {
            return;
        }

        $severity = $event->status === 'completed'
            ? SystemNotificationPayload::SEVERITY_INFO
            : SystemNotificationPayload::SEVERITY_ERROR;

        $body = "**Session:** #{$event->sessionId}\n"
            ."**Phase:** {$event->fromPhase} → {$event->toPhase}\n"
            ."**Status:** {$event->status}";

        $this->dispatcher->dispatch(new SystemNotificationPayload(
            type: 'interrogation.phase_changed',
            userId: $session->user_id,
            severity: $severity,
            title: "Requirements discovery {$event->status}",
            body: $body,
            context: [
                'interrogation_session_id' => $event->sessionId,
                'from_phase' => $event->fromPhase,
                'to_phase' => $event->toPhase,
                'status' => $event->status,
            ],
        ));
    }
}
