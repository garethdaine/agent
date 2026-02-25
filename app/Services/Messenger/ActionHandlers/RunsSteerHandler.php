<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class RunsSteerHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual run steering once Run model is available

        $runId = $parameters['run_id'] ?? null;
        $guidance = $parameters['guidance'] ?? null;

        if ($runId === null) {
            return ActionResult::failure('Run ID is required');
        }

        if ($guidance === null || trim($guidance) === '') {
            return ActionResult::failure('Guidance message is required');
        }

        // Placeholder - in real implementation, would send guidance to running process

        return ActionResult::success(
            data: [
                'run_id' => $runId,
                'guidance' => $guidance,
                'sent_at' => now()->toIso8601String(),
            ],
            message: "Guidance sent to run {$runId}"
        );
    }

    public function validate(array $parameters): array
    {
        $errors = [];

        if (! isset($parameters['run_id']) || ! is_string($parameters['run_id']) || trim($parameters['run_id']) === '') {
            $errors['run_id'] = 'Run ID is required';
        }

        if (! isset($parameters['guidance']) || ! is_string($parameters['guidance']) || trim($parameters['guidance']) === '') {
            $errors['guidance'] = 'Guidance message is required';
        }

        return $errors;
    }
}
