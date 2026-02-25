<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class RunsListActiveHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual run listing once Run model is available
        // For now, return a placeholder response

        $jobId = $parameters['job_id'] ?? null;
        $limit = (int) ($parameters['limit'] ?? 20);

        // Placeholder data - replace with actual run query
        $runs = [
            [
                'id' => 'run-001',
                'job_id' => 'job-001',
                'job_name' => 'Daily Backup',
                'status' => 'running',
                'started_at' => now()->subMinutes(5)->toIso8601String(),
                'progress' => 45,
            ],
            [
                'id' => 'run-002',
                'job_id' => 'job-002',
                'job_name' => 'Hourly Sync',
                'status' => 'running',
                'started_at' => now()->subMinutes(2)->toIso8601String(),
                'progress' => 80,
            ],
        ];

        if ($jobId !== null) {
            $runs = array_filter($runs, fn ($run) => $run['job_id'] === $jobId);
        }

        $runs = array_slice($runs, 0, $limit);

        return ActionResult::success(
            data: ['runs' => array_values($runs), 'total' => count($runs)],
            message: count($runs) > 0
                ? sprintf('%d active run(s)', count($runs))
                : 'No active runs'
        );
    }

    public function validate(array $parameters): array
    {
        $errors = [];

        if (isset($parameters['limit'])) {
            $limit = $parameters['limit'];
            if (! is_numeric($limit) || (int) $limit < 1 || (int) $limit > 100) {
                $errors['limit'] = 'Limit must be between 1 and 100';
            }
        }

        return $errors;
    }
}
