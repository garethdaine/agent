<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class RunsRunNowHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual job execution once Job model is available

        $jobId = $parameters['job_id'] ?? null;

        if ($jobId === null) {
            return ActionResult::failure('Job ID is required');
        }

        // Placeholder - in real implementation, would trigger job execution

        return ActionResult::success(
            data: [
                'job_id' => $jobId,
                'run_id' => 'run-'.substr(md5(uniqid()), 0, 8),
                'status' => 'queued',
                'queued_at' => now()->toIso8601String(),
            ],
            message: "Job {$jobId} has been queued for immediate execution"
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
