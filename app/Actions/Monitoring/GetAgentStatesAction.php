<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\AgentJobRun;
use App\Models\DelegationAttempt;
use App\Models\DelegationTask;
use App\Models\OrgAgentProfile;
use App\Models\User;

class GetAgentStatesAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(User $user): array
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
                    'job_name' => $run->job->name ?? 'Unknown',
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

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, AgentJobRun>  $activeRuns
     * @param  \Illuminate\Support\Collection<int|string, DelegationTask>  $activeDelegationTasks
     * @return array<int|string, AgentJobRun>
     */
    private function buildDelegateeRunMap($activeRuns, $activeDelegationTasks): array
    {
        if ($activeRuns->isEmpty()) {
            return [];
        }

        $runIds = $activeRuns->pluck('id')->filter()->all();
        if (empty($runIds)) {
            return [];
        }

        $attempts = DelegationAttempt::query()
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
}
