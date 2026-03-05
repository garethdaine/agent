<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AgentJobRunFinished;
use App\Jobs\Agent\DeliverWebhookJob;

class DeliverRunWebhook
{
    public function handle(AgentJobRunFinished $event): void
    {
        if (! config('agent.webhooks.enabled', false)) {
            return;
        }

        $url = config('agent.webhooks.url');
        if (! $url) {
            return;
        }

        $allowedEvents = config('agent.webhooks.events', []);
        if (! empty($allowedEvents) && ! in_array('run.finished', $allowedEvents, true)) {
            return;
        }

        $run = $event->run;
        $job = $run->job;

        $payload = [
            'event' => 'run.finished',
            'timestamp' => now()->toIso8601String(),
            'run' => [
                'id' => $run->id,
                'status' => $event->status,
                'job_name' => $job?->name,
                'job_id' => $run->agent_job_id,
                'runner_type' => $job?->runner_type,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'duration_ms' => $run->duration_ms,
                'exit_code' => $run->exit_code,
            ],
        ];

        DeliverWebhookJob::dispatch(
            $url,
            $payload,
            config('agent.webhooks.secret'),
        );
    }
}
