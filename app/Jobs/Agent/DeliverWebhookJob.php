<?php

declare(strict_types=1);

namespace App\Jobs\Agent;

use App\Services\Agent\WebhookDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        private readonly string $url,
        private readonly array $payload,
        private readonly ?string $secret = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(WebhookDeliveryService $service): void
    {
        $service->deliver($this->url, $this->payload, $this->secret);
    }
}
