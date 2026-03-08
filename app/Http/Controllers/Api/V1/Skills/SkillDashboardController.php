<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Skills;

use App\Http\Controllers\Controller;
use App\Models\AgentSkill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SkillDashboardController extends Controller
{
    public function usage(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        $teamId = $team->id;
        $since = now()->subDays(30);

        $skillEvents = DB::table('telemetry_event_ledger')
            ->where('event_type', 'like', 'skill.%')
            ->where('ingested_at', '>=', $since)
            ->whereRaw("payload_json::jsonb->>'team_id' = ?", [(string) $teamId])
            ->get();

        $grouped = $skillEvents->groupBy(function ($event) {
            $payload = json_decode($event->payload_json, true);

            return $payload['skill_name'] ?? 'unknown';
        });

        $data = $grouped->map(function ($events, $skillName) {
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

        return response()->json(['data' => $data]);
    }

    public function health(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        $teamId = $team->id;

        $skills = AgentSkill::query()
            ->where(function ($query) use ($teamId) {
                $query->forTeam($teamId)->orWhere->global();
            })
            ->get();

        $data = $skills->map(fn (AgentSkill $skill) => [
            'id' => $skill->id,
            'name' => $skill->name,
            'status' => $skill->status,
            'risk_level' => $skill->risk_level,
            'validation_pass' => $skill->validation_result['overall_pass'] ?? false,
            'invocation_count' => $skill->invocation_count,
            'last_invoked_at' => $skill->last_invoked_at?->toIso8601String(),
            'has_drift' => $skill->status === AgentSkill::STATUS_PENDING_REVIEW,
        ])->values()->all();

        return response()->json(['data' => $data]);
    }
}
