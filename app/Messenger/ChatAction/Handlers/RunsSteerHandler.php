<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction\Handlers;

use App\Messenger\ChatAction\ChatActionContext;
use App\Messenger\ChatAction\ChatActionResult;
use App\Models\AgentJobRun;
use App\Models\User;

class RunsSteerHandler implements ChatActionHandlerInterface
{
    private const CREDENTIAL_FIELDS = [
        'token',
        'secret',
        'api_key',
        'password',
        'credentials',
        'private_key',
        'access_token',
        'refresh_token',
    ];

    public function handle(ChatActionContext $context): ChatActionResult
    {
        $user = $context->getAuthenticatedUser();
        $run = $context->getTargetRun();
        $params = $context->getParameters();

        if ($run === null) {
            return ChatActionResult::failure('No target run specified');
        }

        if ($run->status !== AgentJobRun::STATUS_RUNNING) {
            return ChatActionResult::failure(
                "Run {$run->id} is not active and cannot be steered"
            );
        }

        if ($this->requiresConfirmation($user, 'steer') && ! $context->isConfirmed()) {
            $jobName = $run->job?->name ?? 'Unknown';

            return ChatActionResult::pendingConfirmation(
                "Are you sure you want to steer run {$run->id} for job '{$jobName}'? Reply 'yes' to confirm.",
                ['run_id' => $run->id, 'action' => 'steer']
            );
        }

        $mutableParams = $this->filterCredentialFields($params);

        unset($mutableParams['run_id']);

        if (empty($mutableParams)) {
            return ChatActionResult::failure('No valid parameters to update');
        }

        $run->job->update($mutableParams);

        return ChatActionResult::success(
            "Run {$run->id} parameters updated: ".implode(', ', array_keys($mutableParams)),
            ['updated_fields' => array_keys($mutableParams)]
        );
    }

    private function filterCredentialFields(array $params): array
    {
        return array_diff_key($params, array_flip(self::CREDENTIAL_FIELDS));
    }

    private function requiresConfirmation(User $user, string $action): bool
    {
        return $user->requiresConfirmationFor($action);
    }
}
