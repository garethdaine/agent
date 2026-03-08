<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Jobs\ExecuteAgentRunJob;
use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJobRun;

class RunsRetryHandler implements ChatActionHandlerInterface
{
    private const RETRIABLE_STATUSES = [
        AgentJobRun::STATUS_FAILED,
        AgentJobRun::STATUS_KILLED,
        AgentJobRun::STATUS_TIMED_OUT,
    ];

    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $run = $context->getTargetRun();

        if ($run === null) {
            return ChatActionResult::failure('No target run specified');
        }

        if (! in_array($run->status, self::RETRIABLE_STATUSES, true)) {
            return ChatActionResult::failure(
                "Run {$run->id} cannot be retried (current status: {$run->status})"
            );
        }

        $newRun = AgentJobRun::create([
            'agent_job_id' => $run->agent_job_id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'status' => AgentJobRun::STATUS_QUEUED,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
        ]);

        dispatch(new ExecuteAgentRunJob($newRun->id));

        $jobName = $run->job?->name ?? 'Unknown';

        return ChatActionResult::success(
            "Retry queued for job '{$jobName}' (New Run ID: {$newRun->id})",
            ['run' => $newRun->toArray()]
        );
    }
}
