<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Carbon\Carbon;

/**
 * Handler for the /runs slash command.
 *
 * Manages agent job runs (list active/history, show, stop, retry, steer).
 */
final class RunsCommandHandler implements SlashCommandHandlerInterface
{
    private const RETRIABLE_STATUSES = [
        AgentJobRun::STATUS_FAILED,
        AgentJobRun::STATUS_KILLED,
        AgentJobRun::STATUS_TIMED_OUT,
    ];

    /**
     * @param  array<int, string>  $args  [subcommand, ...option values]
     */
    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        $subcommand = $args[0] ?? null;
        if ($subcommand === null || $subcommand === '') {
            return $this->usage();
        }

        return match (strtolower($subcommand)) {
            'active' => $this->listActive($user),
            'history' => $this->history($user, $args[1] ?? null, $args[2] ?? null),
            'show' => $this->show($user, $args[1] ?? null),
            'stop' => $this->stop($user, $args[1] ?? null),
            'retry' => $this->retry($user, $args[1] ?? null),
            'steer' => $this->steer($user, $args[1] ?? null, array_slice($args, 2)),
            default => $this->usage(),
        };
    }

    private function usage(): CommandResult
    {
        return CommandResult::failure(
            "Usage: /runs <active|history|show|stop|retry|steer> [args]\n".
            "  active - List active runs\n".
            "  history [job_id] [limit] - Run history\n".
            "  show <run_id> - Run details\n".
            "  stop <run_id> - Stop a run\n".
            "  retry <run_id> - Retry a failed run\n".
            '  steer <run_id> <guidance> - Steer a running process'
        );
    }

    private function listActive(User $user): CommandResult
    {
        $runs = AgentJobRun::whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->active()
            ->with('job:id,name')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get(['id', 'agent_job_id', 'status', 'started_at', 'pid']);

        if ($runs->isEmpty()) {
            return CommandResult::success('No active runs.', ['runs' => []]);
        }

        $lines = ["Active runs ({$runs->count()}):"];
        foreach ($runs as $run) {
            $jobName = $run->job->name ?? 'Unknown';
            $lines[] = "• [{$run->id}] {$jobName} ({$run->status})";
        }

        return CommandResult::success(implode("\n", $lines), ['runs' => $runs->toArray()]);
    }

    private function history(User $user, ?string $jobId, ?string $limitStr): CommandResult
    {
        $query = AgentJobRun::whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->with('job:id,name');

        if ($jobId !== null && $jobId !== '') {
            $query->where('agent_job_id', $jobId);
        }

        $limit = 10;
        if ($limitStr !== null && $limitStr !== '') {
            $limit = min(max(1, (int) $limitStr), 25);
        }

        $runs = $query->orderByDesc('created_at')->limit($limit)->get([
            'id', 'agent_job_id', 'status', 'trigger', 'started_at', 'finished_at', 'duration_ms', 'exit_code',
        ]);

        if ($runs->isEmpty()) {
            $jobContext = $jobId ? " for job `{$jobId}`" : '';

            return CommandResult::success("No run history found{$jobContext}.");
        }

        $lines = [];
        foreach ($runs as $run) {
            $jobName = $run->job->name ?? 'Unknown';
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

        $header = $jobId
            ? "Run history for job `{$jobId}` ({$runs->count()} runs):"
            : "Recent run history ({$runs->count()} runs):";

        return CommandResult::success($header."\n".implode("\n", $lines));
    }

    private function show(User $user, ?string $runId): CommandResult
    {
        if ($runId === null || $runId === '') {
            return CommandResult::failure('Usage: /runs show <run_id>');
        }

        $run = AgentJobRun::with('job:id,name,runner_type,working_directory')
            ->whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->find($runId);

        if ($run === null) {
            return CommandResult::failure("Run `{$runId}` not found.");
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
        $jobName = $run->job->name ?? 'Unknown';
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

        return CommandResult::success(implode("\n", $lines));
    }

    private function stop(User $user, ?string $runId): CommandResult
    {
        if ($runId === null || $runId === '') {
            return CommandResult::failure('Usage: /runs stop <run_id>');
        }

        $run = AgentJobRun::with('job:id,name')
            ->whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->find($runId);

        if ($run === null) {
            return CommandResult::failure("Run `{$runId}` not found.");
        }
        if (! in_array($run->status, AgentJobRun::ACTIVE_STATUSES, true)) {
            return CommandResult::failure("Run {$run->id} is not active (current status: {$run->status})");
        }

        $run->update([
            'status' => AgentJobRun::STATUS_KILLED,
            'finished_at' => now(),
        ]);

        return CommandResult::success("Run {$run->id} stopped successfully");
    }

    private function retry(User $user, ?string $runId): CommandResult
    {
        if ($runId === null || $runId === '') {
            return CommandResult::failure('Usage: /runs retry <run_id>');
        }

        $run = AgentJobRun::with('job:id,name')
            ->whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->find($runId);

        if ($run === null) {
            return CommandResult::failure("Run `{$runId}` not found.");
        }
        if (! in_array($run->status, self::RETRIABLE_STATUSES, true)) {
            return CommandResult::failure("Run {$run->id} cannot be retried (current status: {$run->status})");
        }

        $newRun = AgentJobRun::create([
            'agent_job_id' => $run->agent_job_id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'status' => AgentJobRun::STATUS_QUEUED,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
        ]);
        dispatch(new ExecuteAgentRunJob($newRun->id));

        $jobName = $run->job->name ?? 'Unknown';

        return CommandResult::success(
            "Retry queued for job '{$jobName}' (New Run ID: {$newRun->id})",
            ['run' => $newRun->toArray()]
        );
    }

    /**
     * @param  array<int, string>  $guidanceParts
     */
    private function steer(User $user, ?string $runId, array $guidanceParts): CommandResult
    {
        if ($runId === null || $runId === '') {
            return CommandResult::failure('Usage: /runs steer <run_id> <guidance>');
        }
        $guidance = trim(implode(' ', $guidanceParts));
        if ($guidance === '') {
            return CommandResult::failure('Usage: /runs steer <run_id> <guidance>');
        }

        $run = AgentJobRun::with('job')
            ->whereHas('job', fn ($q) => $q->where('user_id', $user->id))
            ->find($runId);

        if ($run === null) {
            return CommandResult::failure("Run `{$runId}` not found.");
        }
        if ($run->status !== AgentJobRun::STATUS_RUNNING) {
            return CommandResult::failure("Run {$run->id} is not active and cannot be steered");
        }

        return CommandResult::success(
            "Steer received for run {$run->id}: \"{$guidance}\"",
            ['run_id' => $run->id]
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
