<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJobRun;
use Carbon\Carbon;

class RunsShowHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $params = $context->getParameters();

        $runId = $params['run_id'] ?? null;
        if ($runId === null) {
            return ChatActionResult::failure('Run ID is required.');
        }

        $run = AgentJobRun::with('job:id,name,runner_type,working_directory')
            ->whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->find($runId);

        if ($run === null) {
            return ChatActionResult::failure("Run `{$runId}` not found.");
        }

        $icon = match ($run->status) {
            'succeeded' => '✅',
            'failed' => '❌',
            'killed' => '🛑',
            'timed_out' => '⏰',
            'running' => '🔄',
            'queued', 'starting' => '⏳',
            default => '❓',
        };

        $jobName = $run->job?->name ?? 'Unknown';
        $duration = $run->duration_ms ? $this->formatDuration($run->duration_ms) : '-';
        $startedAt = $run->started_at ? Carbon::parse($run->started_at)->format('M j, Y g:ia') : '-';
        $finishedAt = $run->finished_at ? Carbon::parse($run->finished_at)->format('M j, Y g:ia') : '-';

        $lines = [
            "{$icon} **Run `{$run->id}`** — {$run->status}",
            '',
            "**Job:** {$jobName} (`{$run->agent_job_id}`)",
            "**Trigger:** {$run->trigger}",
            "**Started:** {$startedAt}",
            "**Finished:** {$finishedAt}",
            "**Duration:** {$duration}",
        ];

        if ($run->exit_code !== null) {
            $lines[] = "**Exit Code:** `{$run->exit_code}`";
        }

        if ($run->error_summary) {
            $lines[] = '';
            $lines[] = "**Error:** {$run->error_summary}";
        }

        return ChatActionResult::success(implode("\n", $lines));
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
