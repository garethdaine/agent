<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\AgentJob;
use App\Models\User;

class GetJobsSummaryAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(User $user): array
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
}
