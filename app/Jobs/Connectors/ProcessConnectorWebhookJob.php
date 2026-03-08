<?php

declare(strict_types=1);

namespace App\Jobs\Connectors;

use App\Models\AgentConnectorWebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessConnectorWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 45];

    public function __construct(
        public AgentConnectorWebhookEvent $webhookEvent,
    ) {
        $this->queue = 'connector-webhooks';
    }

    public function handle(): void
    {
        $startTime = hrtime(true);

        $this->webhookEvent->update([
            'processing_status' => AgentConnectorWebhookEvent::STATUS_PROCESSING,
        ]);

        // Phase 1: No workflow routing logic — mark as routed.
        // Phase 2 will add event-to-workflow matching here.
        $this->webhookEvent->update([
            'processing_status' => AgentConnectorWebhookEvent::STATUS_ROUTED,
            'processing_duration_ms' => (int) ((hrtime(true) - $startTime) / 1_000_000),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->webhookEvent->update([
            'processing_status' => AgentConnectorWebhookEvent::STATUS_DEAD_LETTER,
            'error_message' => mb_substr($exception->getMessage(), 0, 500),
            'retry_count' => ($this->webhookEvent->retry_count ?? 0) + 1,
        ]);
    }
}
