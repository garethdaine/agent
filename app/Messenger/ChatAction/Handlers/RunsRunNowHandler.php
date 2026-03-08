<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Jobs\ExecuteAgentRunJob;
use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJobRun;

class RunsRunNowHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $job = $context->getTargetJob();

        if ($job === null) {
            return ChatActionResult::failure('No target job specified');
        }

        $run = AgentJobRun::create([
            'agent_job_id' => $job->id,
            'user_id' => $user->id,
            'initiated_by_user_id' => $user->id,
            'status' => AgentJobRun::STATUS_QUEUED,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
        ]);

        dispatch(new ExecuteAgentRunJob($run->id));

        return ChatActionResult::success(
            "Job '{$job->name}' queued for immediate execution (Run ID: {$run->id})",
            ['run' => $run->toArray()]
        );
    }
}
