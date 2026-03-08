<?php

declare(strict_types=1);

namespace App\Jobs\Connectors;

use App\Models\AgentConnectorApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireConnectorApprovalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct()
    {
        $this->onQueue('connector-approvals');
    }

    public function handle(): void
    {
        AgentConnectorApproval::where('status', AgentConnectorApproval::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->update(['status' => AgentConnectorApproval::STATUS_EXPIRED]);
    }
}
