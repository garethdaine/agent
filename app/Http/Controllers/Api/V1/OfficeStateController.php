<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\ConnectorAccount;
use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\MemoryConversationLog;
use App\Models\OrgAgentProfile;
use App\Models\OrgEscalation;
use App\Models\Runtime\RuntimeToolCall;
use App\Models\SchedulerHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
                'messenger' => $this->buildMessengerState(),
                'memory' => $this->buildMemoryState($user),
                'jobs' => $this->buildJobsSummary($user),
                'tools' => $this->buildToolsState($user),
                'escalations' => $this->buildEscalationsState($user),
                'recent_activity' => $this->buildRecentActivity($user),
            ],
        ]);
    }

    private function buildAgentStates($user): array
    {
        $orgAgents = OrgAgentProfile::query()
            ->forUser($user->id)
            ->active()
            ->with(['reportingEdge', 'delegateeProfile'])
            ->get();

        $activeRuns = AgentJobRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['queued', 'starting', 'running', 'stopping'])
            ->with('job:id,name,runner_type')
            ->get();

        $activeDelegationTasks = DelegationTask::query()
            ->whereHas('graph', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['assigned', 'running', 'verifying'])
            ->get()
            ->keyBy('assigned_delegatee_profile_id');

        $delegateeToRunMap = $this->buildDelegateeRunMap($activeRuns, $activeDelegationTasks);

        return $orgAgents->map(function (OrgAgentProfile $agent) use ($activeDelegationTasks, $delegateeToRunMap) {
            $delegationTask = $activeDelegationTasks->get($agent->delegatee_profile_id);

            $run = $delegateeToRunMap[$agent->delegatee_profile_id] ?? null;

            $status = 'idle';
            $activity = 'idle';
            $zone = 'workstation';
            $needsAttention = false;

            if ($delegationTask) {
                $status = $delegationTask->status;
                $activity = match ($delegationTask->status) {
                    'running' => $this->inferActivityFromTaskName($delegationTask->name),
                    'assigned' => 'waiting',
                    'verifying' => 'reviewing',
                    default => 'idle',
                };
                $zone = match ($agent->role_slug) {
                    'coordinator', 'manager' => 'conference',
                    'adversarial_reviewer' => 'warRoom',
                    default => 'workstation',
                };
            } elseif ($run) {
                $status = $run->status;
                $activity = match ($run->status) {
                    'running' => $this->inferActivityFromJobName($run->job?->name),
                    'starting', 'queued' => 'waiting',
                    'stopping' => 'finishing',
                    default => 'idle',
                };
                $needsAttention = $run->status === 'stopping';
            }

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'role' => $agent->role_slug ?? 'agent',
                'status' => $status,
                'current_activity' => $activity,
                'current_run' => $run ? [
                    'id' => $run->id,
                    'job_name' => $run->job?->name ?? 'Unknown',
                    'status' => $run->status,
                    'started_at' => $run->started_at?->toIso8601String(),
                    'duration_ms' => $run->duration_ms,
                ] : null,
                'current_delegation_task' => $delegationTask ? [
                    'id' => $delegationTask->id,
                    'name' => $delegationTask->name,
                    'status' => $delegationTask->status,
                ] : null,
                'current_session' => null,
                'zone' => $zone,
                'tools_active' => [],
                'needs_attention' => $needsAttention,
            ];
        })->values()->toArray();
    }

    private function buildDelegateeRunMap($activeRuns, $activeDelegationTasks): array
    {
        if ($activeRuns->isEmpty()) {
            return [];
        }

        $runIds = $activeRuns->pluck('id')->filter()->all();
        if (empty($runIds)) {
            return [];
        }

        $attempts = \App\Models\DelegationAttempt::query()
            ->whereIn('agent_job_run_id', $runIds)
            ->whereIn('status', ['running', 'pending'])
            ->get(['id', 'delegation_task_id', 'agent_job_run_id']);

        $taskIds = $attempts->pluck('delegation_task_id')->unique()->all();
        $tasks = DelegationTask::whereIn('id', $taskIds)->get(['id', 'assigned_delegatee_profile_id']);

        $runToTask = [];
        foreach ($attempts as $attempt) {
            $runToTask[$attempt->agent_job_run_id] = $tasks->firstWhere('id', $attempt->delegation_task_id);
        }

        $map = [];
        foreach ($activeRuns as $run) {
            $task = $runToTask[$run->id] ?? null;
            $profileId = $task?->assigned_delegatee_profile_id;
            if ($profileId && ! isset($map[$profileId])) {
                $map[$profileId] = $run;
            }
        }

        return $map;
    }

    private function inferActivityFromTaskName(?string $name): string
    {
        if ($name === null) {
            return 'working';
        }

        $lower = strtolower($name);

        return match (true) {
            str_contains($lower, 'review') => 'reviewing',
            str_contains($lower, 'analysis') || str_contains($lower, 'analy') => 'analyzing',
            str_contains($lower, 'synthesis') || str_contains($lower, 'report') => 'compiling_report',
            str_contains($lower, 'test') => 'testing',
            str_contains($lower, 'refactor') => 'refactoring',
            str_contains($lower, 'fix') || str_contains($lower, 'debug') => 'debugging',
            str_contains($lower, 'plan') || str_contains($lower, 'design') => 'planning',
            str_contains($lower, 'write') || str_contains($lower, 'implement') || str_contains($lower, 'build') => 'writing_code',
            default => 'working',
        };
    }

    private function inferActivityFromJobName(?string $name): string
    {
        if ($name === null) {
            return 'working';
        }

        if (str_starts_with($name, 'Delegation: ')) {
            return $this->inferActivityFromTaskName(
                preg_replace('/^Delegation:\s*|\s*\[g\d+]\s*#\d+$/', '', $name)
            );
        }

        return 'executing_job';
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

        $queuedOldest = AgentJobRun::query()
            ->where('status', 'queued')
            ->orderBy('created_at')
            ->value('created_at');

        $queueLag = $queuedOldest ? (int) now()->diffInSeconds($queuedOldest) : 0;

        return [
            'scheduler_healthy' => $schedulerHealthy,
            'queue_lag_seconds' => $queueLag,
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

    private function buildMessengerState(): array
    {
        $connectors = ConnectorAccount::query()
            ->whereNull('deleted_at')
            ->get();

        return [
            'channels' => $connectors->map(fn (ConnectorAccount $c) => [
                'id' => $c->id,
                'platform' => $c->provider,
                'name' => $c->name,
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

    private function buildJobsSummary($user): array
    {
        $jobs = AgentJob::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'is_enabled', 'runner_type', 'cron_expression', 'governance_paused_at']);

        $enabled = $jobs->where('is_enabled', true)->whereNull('governance_paused_at');
        $paused = $jobs->whereNotNull('governance_paused_at');

        return [
            'total' => $jobs->count(),
            'enabled' => $enabled->count(),
            'disabled' => $jobs->count() - $enabled->count(),
            'governance_paused' => $paused->count(),
            'by_runner' => $jobs->groupBy('runner_type')->map->count()->toArray(),
            'list' => $jobs->take(10)->map(fn (AgentJob $j) => [
                'id' => $j->id,
                'name' => $j->name,
                'enabled' => $j->is_enabled && ! $j->governance_paused_at,
                'runner_type' => $j->runner_type,
                'cron' => $j->cron_expression,
            ])->values()->toArray(),
        ];
    }

    private function buildToolsState($user): array
    {
        if (! Schema::hasTable('runtime_tool_calls')) {
            return [
                'recent_calls' => [],
                'pending_approvals' => 0,
                'unique_tools_24h' => 0,
                'total_calls_24h' => 0,
            ];
        }

        $recentCalls = RuntimeToolCall::query()
            ->whereHas('turn.session', fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->limit(15)
            ->get(['id', 'tool_name', 'status', 'duration_ms', 'requires_approval', 'created_at']);

        $pendingApprovals = RuntimeToolCall::query()
            ->whereHas('turn.session', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'pending_approval')
            ->count();

        $uniqueTools = RuntimeToolCall::query()
            ->whereHas('turn.session', fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', now()->subDay())
            ->distinct('tool_name')
            ->count('tool_name');

        $totalCalls = RuntimeToolCall::query()
            ->whereHas('turn.session', fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'recent_calls' => $recentCalls->map(fn (RuntimeToolCall $tc) => [
                'id' => $tc->id,
                'tool_name' => $tc->tool_name,
                'status' => $tc->status?->value ?? (string) $tc->status,
                'duration_ms' => $tc->duration_ms,
                'requires_approval' => $tc->requires_approval,
                'timestamp' => $tc->created_at?->toIso8601String(),
            ])->toArray(),
            'pending_approvals' => $pendingApprovals,
            'unique_tools_24h' => $uniqueTools,
            'total_calls_24h' => $totalCalls,
        ];
    }

    private function buildEscalationsState($user): array
    {
        $pendingOrg = 0;
        $recentItems = [];

        if (Schema::hasTable('org_escalations')) {
            $pendingOrg = OrgEscalation::query()
                ->where('state', OrgEscalation::STATE_PENDING)
                ->count();

            $recentItems = OrgEscalation::query()
                ->with('escalatedToAgent:id,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (OrgEscalation $e) => [
                    'id' => $e->id,
                    'type' => $e->escalation_type,
                    'state' => $e->state,
                    'agent' => $e->escalatedToAgent?->name,
                    'timestamp' => $e->created_at?->toIso8601String(),
                ])->toArray();
        }

        $openIncidents = 0;
        if (Schema::hasTable('agent_projection.escalation_incidents')) {
            try {
                $openIncidents = \App\Models\EscalationIncident::query()
                    ->whereNull('resolved_at')
                    ->count();
            } catch (\Throwable) {
                $openIncidents = 0;
            }
        }

        $runEscalations = AgentJobRun::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [AgentJobRun::STATUS_RUNNING, AgentJobRun::STATUS_FAILED])
            ->with('job:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->filter(function (AgentJobRun $run) {
                $meta = is_array($run->metadata_json) ? $run->metadata_json : [];

                return ($meta['rate_limit_detected'] ?? false) === true
                    || ($meta['approval_required'] ?? false) === true
                    || ($meta['permission_blocker_detected'] ?? false) === true
                    || ($meta['clarification_required'] ?? false) === true;
            })
            ->take(5)
            ->map(function (AgentJobRun $run) {
                $meta = is_array($run->metadata_json) ? $run->metadata_json : [];
                $type = match (true) {
                    ($meta['rate_limit_detected'] ?? false) === true => 'rate_limit',
                    ($meta['approval_required'] ?? false) === true => 'approval_required',
                    ($meta['permission_blocker_detected'] ?? false) === true => 'permission_blocked',
                    ($meta['clarification_required'] ?? false) === true => 'clarification_required',
                    default => 'unknown',
                };

                return [
                    'id' => 'run-'.$run->id,
                    'type' => $type,
                    'state' => $run->status === AgentJobRun::STATUS_RUNNING ? 'active' : 'resolved',
                    'agent' => $run->job?->name,
                    'timestamp' => $run->updated_at?->toIso8601String(),
                    'run_id' => $run->id,
                ];
            })
            ->values()
            ->toArray();

        $allItems = array_merge($recentItems, $runEscalations);
        usort($allItems, fn ($a, $b) => ($b['timestamp'] ?? '') <=> ($a['timestamp'] ?? ''));
        $allItems = array_slice($allItems, 0, 10);

        $runIncidents = count(array_filter($runEscalations, fn ($e) => $e['state'] === 'active'));

        return [
            'open_incidents' => $openIncidents + $runIncidents,
            'pending_org_escalations' => $pendingOrg,
            'recent_items' => $allItems,
        ];
    }

    private function buildRecentActivity($user): array
    {
        $recentRuns = AgentJobRun::query()
            ->where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->with('job:id,name')
            ->latest('finished_at')
            ->limit(10)
            ->get();

        return $recentRuns->map(fn (AgentJobRun $run) => [
            'type' => match ($run->status) {
                'succeeded' => 'success',
                'failed', 'timed_out', 'killed' => 'failure',
                default => 'info',
            },
            'label' => ($run->job?->name ?? 'Job').' — '.str_replace('_', ' ', $run->status),
            'status' => $run->status,
            'job_name' => $run->job?->name,
            'duration_ms' => $run->duration_ms,
            'timestamp' => $run->finished_at?->toIso8601String(),
        ])->toArray();
    }
}
