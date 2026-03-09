<?php

declare(strict_types=1);

namespace App\Actions\Skills;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetSkillUsageStatsAction
{
    /**
     * @return list<array{skill_id: mixed, skill_name: string, invocation_count: int, success_rate: float, avg_duration_ms: int}>
     */
    public function execute(int $teamId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $skillEvents = DB::table('telemetry_event_ledger')
            ->where('event_type', 'like', 'skill.%')
            ->where('ingested_at', '>=', $since)
            ->whereRaw("payload_json::jsonb->>'team_id' = ?", [(string) $teamId])
            ->get();

        $grouped = $skillEvents->groupBy(function ($event) {
            $payload = json_decode($event->payload_json, true);

            return $payload['skill_name'] ?? 'unknown';
        });

        return $grouped->map(function (Collection $events, string $skillName): array {
            $total = $events->count();
            $succeeded = $events->filter(fn ($e) => $e->event_type === 'skill.completed')->count();
            $totalDuration = $events->sum(function ($e) {
                $payload = json_decode($e->payload_json, true);

                return $payload['duration_ms'] ?? 0;
            });

            $firstPayload = json_decode($events->first()->payload_json, true);

            return [
                'skill_id' => $firstPayload['skill_id'] ?? null,
                'skill_name' => $skillName,
                'invocation_count' => $total,
                'success_rate' => $total > 0 ? round(($succeeded / $total) * 100, 1) : 0.0,
                'avg_duration_ms' => $total > 0 ? (int) round($totalDuration / $total) : 0,
            ];
        })
            ->sortByDesc('invocation_count')
            ->values()
            ->take(10)
            ->all();
    }
}
