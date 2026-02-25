<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class JobsDeleteHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual job deletion once Job model is available

        $jobId = $parameters['job_id'] ?? null;

        if ($jobId === null) {
            return ActionResult::failure('Job ID is required');
        }

        // Placeholder - in real implementation, would delete the job

        return ActionResult::success(
            data: [
                'job_id' => $jobId,
                'deleted_at' => now()->toIso8601String(),
            ],
            message: "Job {$jobId} deleted successfully"
        );
    }

    public function validate(array $parameters): array
    {
        $errors = [];

        if (! isset($parameters['job_id']) || ! is_string($parameters['job_id']) || trim($parameters['job_id']) === '') {
            $errors['job_id'] = 'Job ID is required';
        }

        return $errors;
    }
}
