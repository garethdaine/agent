<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJob;
use Carbon\Carbon;

class JobsShowHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $params = $context->getParameters();

        $jobId = $params['job_id'] ?? null;
        if ($jobId === null) {
            return ChatActionResult::failure('Job ID is required.');
        }

        $job = AgentJob::where('user_id', $user->id)->find($jobId);

        if ($job === null) {
            return ChatActionResult::failure("Job `{$jobId}` not found.");
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
            $lastRunIcon = match ($lastRun->status) { // @phpstan-ignore property.notFound
                'succeeded' => '✅',
                'failed' => '❌',
                default => '❓',
            };
            $lastRunTime = Carbon::parse($lastRun->created_at)->diffForHumans(); // @phpstan-ignore property.notFound
            $lines[] = "**Last Run:** {$lastRunIcon} {$lastRun->status} ({$lastRunTime})";
        }

        return ChatActionResult::success(implode("\n", $lines));
    }
}
