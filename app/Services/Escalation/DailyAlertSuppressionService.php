<?php

declare(strict_types=1);

namespace App\Services\Escalation;

use App\Support\Telemetry\ProjectionTable;
use App\Support\Time\AgentClock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class DailyAlertSuppressionService
{
    public function __construct(
        private readonly AgentClock $clock,
    ) {}

    public function suppressIfAlreadyAlertedToday(
        string $workflowKey,
        string $triggerType,
        ?int $incidentId = null,
    ): bool {
        $now = $this->clock->nowUtc();

        try {
            DB::table(ProjectionTable::qualified('escalation_alert_suppressions'))->insert([
                'workflow_key' => $workflowKey,
                'trigger_type' => $triggerType,
                'suppression_date_utc' => $now->toDateString(),
                'incident_id' => $incidentId,
                'metadata_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return false;
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return true;
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
