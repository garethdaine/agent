<?php

declare(strict_types=1);

namespace App\Support\Connectors\Telemetry;

use App\Models\AgentConnectorInvocation;
use Carbon\Carbon;

class ConnectorReliabilityContributor
{
    /**
     * Calculate the connector reliability contribution for a workflow over a time range.
     *
     * Returns a float between 0.0 and 1.0 representing the success rate of
     * connector invocations for the given workflow. A value of 1.0 means all
     * invocations succeeded; 0.0 means all failed.
     *
     * When no invocations exist for the time range, returns 1.0 (no degradation signal).
     */
    public function getReliabilityContribution(string $workflowKey, Carbon $from, Carbon $to): float
    {
        $query = AgentConnectorInvocation::query()
            ->where('workflow_key', $workflowKey)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to);

        $total = $query->count();

        if ($total === 0) {
            return 1.0;
        }

        $successes = (clone $query)
            ->where('outcome', AgentConnectorInvocation::OUTCOME_SUCCESS)
            ->count();

        return round($successes / $total, 2);
    }
}
