<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJobRun;
use App\Models\User;

class RunsStopHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $run = $context->getTargetRun();

        if ($run === null) {
            return ChatActionResult::failure('No target run specified');
        }

        if (! in_array($run->status, AgentJobRun::ACTIVE_STATUSES, true)) {
            return ChatActionResult::failure(
                "Run {$run->id} is not active (current status: {$run->status})"
            );
        }

        if ($this->requiresConfirmation($user, 'stop') && ! $context->isConfirmed()) {
            $jobName = $run->job->name ?? 'Unknown';

            return ChatActionResult::pendingConfirmation(
                "Are you sure you want to stop run {$run->id} for job '{$jobName}'? Reply 'yes' to confirm.",
                ['run_id' => $run->id, 'action' => 'stop']
            );
        }

        $run->update([
            'status' => AgentJobRun::STATUS_KILLED,
            'finished_at' => now(),
        ]);

        return ChatActionResult::success(
            "Run {$run->id} stopped successfully"
        );
    }

    private function requiresConfirmation(User $user, string $action): bool
    {
        return $user->requiresConfirmationFor($action);
    }
}
