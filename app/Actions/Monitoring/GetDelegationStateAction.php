<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\DelegationGraph;
use App\Models\DelegationTask;
use App\Models\User;

class GetDelegationStateAction
{
    /**
     * @return array{active_graphs: int, tasks_running: int, tasks_pending_verification: int}
     */
    public function execute(User $user): array
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
}
