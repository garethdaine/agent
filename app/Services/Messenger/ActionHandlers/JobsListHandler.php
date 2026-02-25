<?php

namespace App\Services\Messenger\ActionHandlers;

use App\DTOs\Messenger\ActionResult;
use App\Models\User;

class JobsListHandler implements ActionHandlerInterface
{
    public function handle(array $parameters, User $user): ActionResult
    {
        // TODO: Implement actual job listing once Job model is available
        // For now, return a placeholder response

        $filter = $parameters['filter'] ?? null;
        $limit = (int) ($parameters['limit'] ?? 20);

        // Placeholder data - replace with actual job query
        $jobs = [
            [
                'id' => 'job-001',
                'name' => 'Daily Backup',
                'schedule' => '0 0 * * *',
                'enabled' => true,
                'last_run' => now()->subHours(12)->toIso8601String(),
            ],
            [
                'id' => 'job-002',
                'name' => 'Hourly Sync',
                'schedule' => '0 * * * *',
                'enabled' => true,
                'last_run' => now()->subMinutes(30)->toIso8601String(),
            ],
        ];

        if ($filter !== null) {
            $jobs = array_filter($jobs, function ($job) use ($filter) {
                return stripos($job['name'], $filter) !== false;
            });
        }

        $jobs = array_slice($jobs, 0, $limit);

        return ActionResult::success(
            data: ['jobs' => array_values($jobs), 'total' => count($jobs)],
            message: count($jobs) > 0
                ? sprintf('Found %d job(s)', count($jobs))
                : 'No jobs found'
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
