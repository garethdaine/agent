<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class RunsRetryHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual run retry once Run model is available

        $runId = $parameters['run_id'] ?? null;
        $withModifications = (bool) ($parameters['with_modifications'] ?? false);

        if ($runId === null) {
            return ActionResult::failure('Run ID is required');
        }

        // Placeholder - in real implementation, would find and retry the run

        return ActionResult::success(
            data: [
                'original_run_id' => $runId,
                'new_run_id' => 'run-'.substr(md5(uniqid()), 0, 8),
                'status' => 'queued',
                'with_modifications' => $withModifications,
                'queued_at' => now()->toIso8601String(),
            ],
            message: "Run {$runId} has been queued for retry"
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
