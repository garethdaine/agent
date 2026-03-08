<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentConnectorInvocation;
use App\Models\AgentConnectorWebhookEvent;
use Illuminate\Console\Command;

class ConnectorPruneTelemetryCommand extends Command
{
    protected $signature = 'connector:prune-telemetry
        {--days= : Override retention period in days}';

    protected $description = 'Delete connector invocations and webhook events older than the retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('connectors.telemetry.retention_days', 90));
        $cutoff = now()->subDays($days);

        $invocationsDeleted = AgentConnectorInvocation::where('created_at', '<', $cutoff)->delete();
        $webhookEventsDeleted = AgentConnectorWebhookEvent::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$invocationsDeleted} invocation(s) and {$webhookEventsDeleted} webhook event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
