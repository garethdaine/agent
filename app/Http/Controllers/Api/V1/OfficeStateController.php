<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentJobRun;
use App\Models\ConnectorAccount;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\MemoryConversationLog;
use App\Models\OrgAgentProfile;
use App\Models\SchedulerHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeStateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'agents' => $this->buildAgentStates($user),
                'system' => $this->buildSystemState(),
                'delegation' => $this->buildDelegationState($user),
                'messenger' => $this->buildMessengerState($user),
                'memory' => $this->buildMemoryState($user),
            ],
        ]);
    }

    private function buildAgentStates($user): array
    {
        $orgAgents = OrgAgentProfile::query()
            ->with(['reportingEdge', 'delegateeProfile'])
            ->get();

        $activeRuns = AgentJobRun::query()
            ->whereIn('status', ['queued', 'starting', 'running', 'stopping'])
            ->with('job:id,name,runner_type')
            ->get();

        return $orgAgents->map(function (OrgAgentProfile $agent) use ($activeRuns) {
            $run = $activeRuns->first();

            $status = 'idle';
            $activity = 'idle';
            $needsAttention = false;

            if ($run) {
                $status = $run->status;
                $activity = match ($run->status) {
                    'running' => 'writing_code',
                    'starting', 'queued' => 'waiting',
                    'stopping' => 'finishing',
                    default => 'idle',
                };
                $needsAttention = $run->status === 'stopping';
            }

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'role' => $agent->role ?? 'agent',
                'status' => $status,
                'current_activity' => $activity,
                'current_run' => $run ? [
                    'id' => $run->id,
                    'job_name' => $run->job?->name ?? 'Unknown',
                    'status' => $run->status,
                    'started_at' => $run->started_at?->toIso8601String(),
                ] : null,
                'current_session' => null,
                'zone' => 'workstation',
                'tools_active' => [],
                'needs_attention' => $needsAttention,
            ];
        })->values()->toArray();
    }

    private function buildSystemState(): array
    {
        $latestHeartbeat = SchedulerHeartbeat::query()
            ->latest('last_seen_at')
            ->first();

        $activeRunCount = AgentJobRun::query()
            ->whereIn('status', ['queued', 'starting', 'running'])
            ->count();

        $schedulerHealthy = $latestHeartbeat
            && $latestHeartbeat->last_seen_at
            && $latestHeartbeat->last_seen_at->diffInMinutes(now()) < 3;

        return [
            'scheduler_healthy' => $schedulerHealthy,
            'queue_lag_seconds' => 0,
            'rate_limited' => false,
            'active_runs' => $activeRunCount,
            'runtime_mode' => config('runtime.default_mode', 'standard'),
        ];
    }

    private function buildDelegationState($user): array
    {
        $activeGraphs = DelegationGraph::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['running', 'validating'])
            ->count();

        $runningTasks = DelegationTask::query()
            ->whereHas('graph', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'running')
            ->count();

        $pendingVerification = DelegationTask::query()
            ->whereHas('graph', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'verifying')
            ->count();

        return [
            'active_graphs' => $activeGraphs,
            'tasks_running' => $runningTasks,
            'tasks_pending_verification' => $pendingVerification,
        ];
    }

    private function buildMessengerState($user): array
    {
        $connectors = ConnectorAccount::query()
            ->where('user_id', $user->id)
            ->get();

        return [
            'channels' => $connectors->map(fn (ConnectorAccount $c) => [
                'platform' => $c->platform,
                'status' => $c->status ?? 'unknown',
                'unread' => 0,
            ])->values()->toArray(),
        ];
    }

    private function buildMemoryState($user): array
    {
        $totalEntries = MemoryConversationLog::query()
            ->where('user_id', $user->id)
            ->count();

        $recentFormations = MemoryConversationLog::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return [
            'total_entries' => $totalEntries,
            'recent_formations' => $recentFormations,
        ];
    }
}
