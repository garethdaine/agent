<?php

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\User;

class JobsDeleteHandler implements ChatActionHandlerInterface
{
    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $job = $context->getTargetJob();

        if ($job === null) {
            return ChatActionResult::failure('No target job specified');
        }

        // Check if confirmation is required
        if ($this->requiresConfirmation($user, 'delete') && ! $context->isConfirmed()) {
            return ChatActionResult::pendingConfirmation(
                "Are you sure you want to delete job '{$job->name}' (ID: {$job->id})? Reply 'yes' to confirm.",
                ['job_id' => $job->id, 'action' => 'delete']
            );
        }

        $jobName = $job->name;
        $jobId = $job->id;

        $job->delete();

        return ChatActionResult::success(
            "Job '{$jobName}' (ID: {$jobId}) deleted successfully"
        );
    }

    private function requiresConfirmation(User $user, string $action): bool
    {
        return $user->requiresConfirmationFor($action);
    }
}
