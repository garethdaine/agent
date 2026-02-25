<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class RunsStopHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual run stopping once Run model is available

        $runId = $parameters['run_id'] ?? null;
        $reason = $parameters['reason'] ?? 'Stopped via messenger';

        if ($runId === null) {
            return ActionResult::failure('Run ID is required');
        }

        // Placeholder - in real implementation, would find and stop the run
        // For now, simulate success

        return ActionResult::success(
            data: [
                'run_id' => $runId,
                'status' => 'stopped',
                'reason' => $reason,
                'stopped_at' => now()->toIso8601String(),
            ],
            message: "Run {$runId} has been stopped"
        );
    }

    public function validate(array $parameters): array
    {
        $errors = [];

        if (! isset($parameters['run_id']) || ! is_string($parameters['run_id']) || trim($parameters['run_id']) === '') {
            $errors['run_id'] = 'Run ID is required';
        }

        return $errors;
    }
}
