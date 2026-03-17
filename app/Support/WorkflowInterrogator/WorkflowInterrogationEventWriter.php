<?php

declare(strict_types=1);

namespace App\Support\WorkflowInterrogator;

use App\Events\WorkflowInterrogationSessionUpdated;
use App\Models\WorkflowInterrogationEvent;
use App\Models\WorkflowInterrogationSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorkflowInterrogationEventWriter
{
    public function __construct(
        private readonly WorkflowInterrogationSession $session,
        private readonly ?WorkflowInterrogationPresenter $presenter = null,
    ) {}

    public function append(string $eventType, array $payload): WorkflowInterrogationEvent
    {
        $event = DB::transaction(function () use ($eventType, $payload): WorkflowInterrogationEvent {
            WorkflowInterrogationSession::query()
                ->whereKey($this->session->id)
                ->lockForUpdate()
                ->first();

            $sequence = (int) ($this->session->events()->max('sequence') ?? 0) + 1;

            return $this->session->events()->create([
                'event_type' => $eventType,
                'sequence' => $sequence,
                'payload' => $payload,
                'event_ts' => CarbonImmutable::now('UTC'),
            ]);
        }, 3);

        $session = $this->session->fresh();
        if ($session !== null) {
            $presenter = $this->presenter ?? app(WorkflowInterrogationPresenter::class);
            $requiresRefresh = in_array($event->event_type, [
                WorkflowInterrogationEvent::TYPE_QUESTION_BATCH,
                WorkflowInterrogationEvent::TYPE_ANSWER_BATCH,
                WorkflowInterrogationEvent::TYPE_SUMMARY,
                WorkflowInterrogationEvent::TYPE_ACTION_PLAN,
                WorkflowInterrogationEvent::TYPE_ERROR,
            ], true);

            event(new WorkflowInterrogationSessionUpdated(
                (int) $session->id,
                [
                    'session_id' => (int) $session->id,
                    'sequence' => (int) $event->sequence,
                    'event_type' => $event->event_type,
                    'payload' => $event->payload ?? [],
                    'event_ts' => $event->event_ts?->toIso8601String(),
                    'session' => $presenter->session($session, false),
                    '_requires_refresh' => $requiresRefresh,
                ],
            ));
        }

        return $event;
    }
}
