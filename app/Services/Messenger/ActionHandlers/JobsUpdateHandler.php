<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class JobsUpdateHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual job update once Job model is available

        $jobId = $parameters['job_id'] ?? null;

        if ($jobId === null) {
            return ActionResult::failure('Job ID is required');
        }

        // Collect update fields
        $updates = [];

        if (isset($parameters['name'])) {
            $updates['name'] = $parameters['name'];
        }

        if (isset($parameters['command'])) {
            $updates['command'] = $parameters['command'];
        }

        if (isset($parameters['schedule'])) {
            if (! $this->isValidCronExpression($parameters['schedule'])) {
                return ActionResult::failure('Invalid cron expression format');
            }
            $updates['schedule'] = $parameters['schedule'];
        }

        if (isset($parameters['enabled'])) {
            $updates['enabled'] = (bool) $parameters['enabled'];
        }

        if (empty($updates)) {
            return ActionResult::failure('No update fields provided');
        }

        // Placeholder - in real implementation, would update the job

        return ActionResult::success(
            data: [
                'job_id' => $jobId,
                'updates' => $updates,
                'updated_at' => now()->toIso8601String(),
            ],
            message: "Job {$jobId} updated successfully"
        );
    }

    public function validate(array $parameters): array
    {
        $errors = [];

        if (! isset($parameters['job_id']) || ! is_string($parameters['job_id']) || trim($parameters['job_id']) === '') {
            $errors['job_id'] = 'Job ID is required';
        }

        if (isset($parameters['schedule']) && ! $this->isValidCronExpression($parameters['schedule'])) {
            $errors['schedule'] = 'Invalid cron expression format';
        }

        return $errors;
    }

    private function isValidCronExpression(mixed $schedule): bool
    {
        if (! is_string($schedule)) {
            return false;
        }

        $parts = preg_split('/\s+/', trim($schedule));

        return is_array($parts) && (count($parts) === 5 || count($parts) === 6);
    }
}
