<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJobRun;
use Carbon\Carbon;

class RunsListHistoryHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $params = $context->getParameters();

        $query = AgentJobRun::whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->with('job:id,name');

        if (isset($params['job_id'])) {
            $query->where('agent_job_id', $params['job_id']);
        }

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        $limit = min((int) ($params['limit'] ?? 10), 25);

        $runs = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'agent_job_id', 'status', 'trigger', 'started_at', 'finished_at', 'duration_ms', 'exit_code']);

        if ($runs->isEmpty()) {
            $jobContext = isset($params['job_id']) ? " for job `{$params['job_id']}`" : '';

            return ChatActionResult::success("No run history found{$jobContext}.");
        }

        $lines = [];
        foreach ($runs as $run) {
            $jobName = $run->job?->name ?? 'Unknown';
            $icon = match ($run->status) {
                'succeeded' => '✅',
                'failed' => '❌',
                'killed' => '🛑',
                'timed_out' => '⏰',
                'running' => '🔄',
                'queued', 'starting' => '⏳',
                default => '❓',
            };
            $duration = $run->duration_ms ? $this->formatDuration($run->duration_ms) : '-';
            $finishedAt = $run->finished_at ? Carbon::parse($run->finished_at)->diffForHumans() : 'in progress';

            $lines[] = "{$icon} `{$run->id}` **{$jobName}** — {$run->status} ({$duration}, {$finishedAt})";
        }

        $header = isset($params['job_id'])
            ? "Run history for job `{$params['job_id']}` ({$runs->count()} runs):"
            : "Recent run history ({$runs->count()} runs):";

        return ChatActionResult::success(
            $header."\n".implode("\n", $lines)
        );
    }

    private function formatDuration(int $ms): string
    {
        if ($ms < 1000) {
            return "{$ms}ms";
        }

        $seconds = intdiv($ms, 1000);

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$remainingSeconds}s";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }
}
