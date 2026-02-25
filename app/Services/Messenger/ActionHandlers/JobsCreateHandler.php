<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class JobsCreateHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual job creation once Job model is available

        $name = $parameters['name'] ?? null;
        $command = $parameters['command'] ?? null;
        $schedule = $parameters['schedule'] ?? null;
        $description = $parameters['description'] ?? '';

        // Validate cron expression format
        if (! $this->isValidCronExpression($schedule)) {
            return ActionResult::failure('Invalid cron expression format');
        }

        // Placeholder - in real implementation, would create the job

        $jobId = 'job-'.substr(md5(uniqid()), 0, 8);

        return ActionResult::success(
            data: [
                'job_id' => $jobId,
                'name' => $name,
                'command' => $command,
                'schedule' => $schedule,
                'description' => $description,
                'enabled' => true,
                'created_at' => now()->toIso8601String(),
            ],
            message: "Job '{$name}' created successfully (ID: {$jobId})"
        );
    }

    public function validate(array $parameters): array
    {
        $errors = [];

        if (! isset($parameters['name']) || ! is_string($parameters['name']) || trim($parameters['name']) === '') {
            $errors['name'] = 'Job name is required';
        }

        if (! isset($parameters['command']) || ! is_string($parameters['command']) || trim($parameters['command']) === '') {
            $errors['command'] = 'Command is required';
        }

        if (! isset($parameters['schedule']) || ! is_string($parameters['schedule']) || trim($parameters['schedule']) === '') {
            $errors['schedule'] = 'Schedule (cron expression) is required';
        } elseif (! $this->isValidCronExpression($parameters['schedule'])) {
            $errors['schedule'] = 'Invalid cron expression format';
        }

        return $errors;
    }

    private function isValidCronExpression(mixed $schedule): bool
    {
        if (! is_string($schedule)) {
            return false;
        }

        // Basic cron expression validation (5 or 6 fields)
        $parts = preg_split('/\s+/', trim($schedule));

        return is_array($parts) && (count($parts) === 5 || count($parts) === 6);
    }
}
