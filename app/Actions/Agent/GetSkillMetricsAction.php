<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GetSkillMetricsAction
{
    /**
     * @return array{skill_failure_rate: float, skill_token_spend: int, skill_escalations: int}
     */
    public function execute(CarbonImmutable $windowStart): array
    {
        $skillEvents = DB::table('telemetry_event_ledger')
            ->where('event_type', 'like', 'skill.%')
            ->where('ingested_at', '>=', $windowStart)
            ->get();

        $totalSkillEvents = $skillEvents->count();
        $failedSkillEvents = $skillEvents->where('event_type', 'skill.failed')->count();

        $skillFailureRate = $totalSkillEvents > 0
            ? round(($failedSkillEvents / $totalSkillEvents) * 100, 1)
            : 0.0;

        $skillTokenSpend = $skillEvents->sum(function ($event) {
            $payload = json_decode($event->payload_json, true);

            return $payload['token_usage'] ?? 0;
        });

        $skillEscalations = $skillEvents->filter(function ($event) {
            $payload = json_decode($event->payload_json, true);

            return ($payload['outcome'] ?? '') === 'failed'
                && ! empty($payload['failure_reason']);
        })->count();

        return [
            'skill_failure_rate' => $skillFailureRate,
            'skill_token_spend' => (int) $skillTokenSpend,
            'skill_escalations' => $skillEscalations,
        ];
    }
}
