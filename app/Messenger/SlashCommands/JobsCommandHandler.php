<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

final class JobsCommandHandler implements SlashCommandHandlerInterface
{
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
            'list' => $this->listJobs($user),
            'show' => $this->showJob($user, $args[1] ?? null),
            'create' => $this->createJob($user, $args[1] ?? null),
            'delete' => $this->deleteJob($user, $args[1] ?? null),
            'run' => $this->runJob($user, $args[1] ?? null),
            'enable' => $this->enableJob($user, $args[1] ?? null),
            'disable' => $this->disableJob($user, $args[1] ?? null),
            default => $this->usage(),
        };
    }

    private function usage(): CommandResult
    {
        return CommandResult::failure(
            "Usage: /jobs <list|show|create|delete|run|enable|disable> [args]\n".
            "  list - List your jobs\n".
            "  show <job_id> - Job details\n".
            "  create <name> - Create a job\n".
            "  delete <job_id> - Delete a job\n".
            "  run <job_id> - Run job now\n".
            "  enable <job_id> - Enable a job\n".
            '  disable <job_id> - Disable a job'
        );
    }

    private function listJobs(User $user): CommandResult
    {
        $jobs = AgentJob::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'is_enabled', 'cron_expression', 'updated_at']);

        if ($jobs->isEmpty()) {
            return CommandResult::success('You have no jobs configured.', ['jobs' => []]);
        }

        $lines = ["Your jobs ({$jobs->count()}):"];
        foreach ($jobs as $job) {
            $status = $job->is_enabled ? 'active' : 'paused';
            $lines[] = "- [{$job->id}] {$job->name} ({$status})";
        }

        return CommandResult::success(implode("\n", $lines), ['jobs' => $jobs->toArray()]);
    }

    private function showJob(User $user, ?string $jobId): CommandResult
    {
        if ($jobId === null || $jobId === '') {
            return CommandResult::failure('Usage: /jobs show <job_id>');
        }

        $job = AgentJob::where('user_id', $user->id)->find($jobId);
        if ($job === null) {
            return CommandResult::failure("Job `{$jobId}` not found.");
        }

        $statusIcon = $job->is_enabled ? '✅' : '⏸️';
        $status = $job->is_enabled ? 'enabled' : 'paused';
        $lastRun = $job->runs()->latest('created_at')->first();

        $lines = [
            "{$statusIcon} **{$job->name}** (`{$job->id}`)",
            '',
            "**Status:** {$status}",
            "**Runner:** {$job->runner_type}",
            "**Schedule:** `{$job->cron_expression}` ({$job->timezone})",
        ];
        if ($job->working_directory) {
            $lines[] = "**Directory:** `{$job->working_directory}`";
        }
        if ($job->description) {
            $lines[] = "**Description:** {$job->description}";
        }
        $totalRuns = $job->runs()->count();
        $succeededRuns = $job->runs()->where('status', 'succeeded')->count();
        $failedRuns = $job->runs()->where('status', 'failed')->count();
        $lines[] = '';
        $lines[] = "**Runs:** {$totalRuns} total, {$succeededRuns} succeeded, {$failedRuns} failed";
        if ($lastRun) {
            $lastRunIcon = match ($lastRun->status) {
                'succeeded' => '✅',
                'failed' => '❌',
                default => '❓',
            };
            $lastRunTime = Carbon::parse($lastRun->created_at)->diffForHumans();
            $lines[] = "**Last Run:** {$lastRunIcon} {$lastRun->status} ({$lastRunTime})";
        }

        return CommandResult::success(implode("\n", $lines));
    }

    private function createJob(User $user, ?string $name): CommandResult
    {
        if ($name === null || trim($name) === '') {
            return CommandResult::failure('Usage: /jobs create <name>');
        }

        $params = ['name' => trim($name)];
        $validation = Validator::make($params, ['name' => 'required|string|max:255']);
        if ($validation->fails()) {
            return CommandResult::failure('Validation failed: '.$validation->errors()->first());
        }

        $job = AgentJob::create([
            'user_id' => $user->id,
            'name' => $params['name'],
            'description' => null,
            'cron_expression' => '0 0 * * *',
            'timezone' => 'UTC',
            'is_enabled' => false,
            'max_runtime_seconds' => 3600,
            'cooldown_seconds' => 0,
            'runner_type' => 'claude',
            'command_template' => '',
            'task_markdown_path' => '',
            'working_directory' => '',
        ]);

        return CommandResult::success(
            "Job **{$job->name}** created successfully (ID: `{$job->id}`)",
            ['job_id' => $job->id, 'name' => $job->name, 'runner_type' => $job->runner_type, 'schedule' => $job->cron_expression]
        );
    }

    private function deleteJob(User $user, ?string $jobId): CommandResult
    {
        if ($jobId === null || $jobId === '') {
            return CommandResult::failure('Usage: /jobs delete <job_id>');
        }

        $job = AgentJob::where('user_id', $user->id)->find($jobId);
        if ($job === null) {
            return CommandResult::failure("Job `{$jobId}` not found.");
        }

        $jobName = $job->name;
        $job->delete();

        return CommandResult::success("Job '{$jobName}' (ID: {$jobId}) deleted successfully");
    }

    private function runJob(User $user, ?string $jobId): CommandResult
    {
        if ($jobId === null || $jobId === '') {
            return CommandResult::failure('Usage: /jobs run <job_id>');
        }

        $job = AgentJob::where('user_id', $user->id)->find($jobId);
        if ($job === null) {
            return CommandResult::failure("Job `{$jobId}` not found.");
        }

        $run = AgentJobRun::create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'status' => AgentJobRun::STATUS_QUEUED,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
        ]);
        dispatch(new ExecuteAgentRunJob($run->id));

        return CommandResult::success(
            "Job '{$job->name}' queued for immediate execution (Run ID: {$run->id})",
            ['run' => $run->toArray()]
        );
    }

    private function enableJob(User $user, ?string $jobId): CommandResult
    {
        if ($jobId === null || $jobId === '') {
            return CommandResult::failure('Usage: /jobs enable <job_id>');
        }

        $job = AgentJob::where('user_id', $user->id)->find($jobId);
        if ($job === null) {
            return CommandResult::failure("Job `{$jobId}` not found.");
        }
        if ($job->is_enabled) {
            return CommandResult::success("Job '{$job->name}' is already enabled.");
        }
        $job->update(['is_enabled' => true]);

        return CommandResult::success("Job '{$job->name}' enabled.");
    }

    private function disableJob(User $user, ?string $jobId): CommandResult
    {
        if ($jobId === null || $jobId === '') {
            return CommandResult::failure('Usage: /jobs disable <job_id>');
        }

        $job = AgentJob::where('user_id', $user->id)->find($jobId);
        if ($job === null) {
            return CommandResult::failure("Job `{$jobId}` not found.");
        }
        if (! $job->is_enabled) {
            return CommandResult::success("Job '{$job->name}' is already disabled.");
        }
        $job->update(['is_enabled' => false]);

        return CommandResult::success("Job '{$job->name}' disabled.");
    }
}
