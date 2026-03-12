<?php

declare(strict_types=1);

namespace App\Services\Delegation;

use App\Models\DelegateeProfile;
use App\Models\DelegationGraph;
use App\Models\DelegationLearning;
use App\Models\DelegationTask;

/**
 * Summarizes outcomes across completed delegations and feeds back into capability matching.
 */
class DelegationLearningAggregator
{
    /**
     * Aggregate learnings for a completed delegation graph and a specific delegatee.
     * Creates or updates a DelegationLearning record summarizing outcomes, success patterns,
     * and failure patterns.
     */
    public function aggregate(DelegationGraph $graph, DelegateeProfile $profile): DelegationLearning
    {
        $tasks = DelegationTask::where('delegation_graph_id', $graph->id)
            ->where('assigned_delegatee_profile_id', $profile->id)
            ->get();

        $outcomeSummary = $this->buildOutcomeSummary($tasks);
        $successPatterns = $this->extractSuccessPatterns($tasks);
        $failurePatterns = $this->extractFailurePatterns($tasks);

        return DelegationLearning::updateOrCreate(
            [
                'delegation_graph_id' => $graph->id,
                'delegatee_profile_id' => $profile->id,
            ],
            [
                'outcome_summary_json' => $outcomeSummary,
                'success_patterns_json' => $successPatterns,
                'failure_patterns_json' => $failurePatterns,
                'aggregated_at' => now(),
            ]
        );
    }

    /**
     * Get aggregated learnings for a specific delegatee across all graphs.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DelegationLearning>
     */
    public function getLearningsForProfile(DelegateeProfile $profile): \Illuminate\Database\Eloquent\Collection
    {
        return DelegationLearning::where('delegatee_profile_id', $profile->id)
            ->orderByDesc('aggregated_at')
            ->get();
    }

    /**
     * Get a capability summary for a delegatee based on historical learnings.
     *
     * @return array{total_tasks: int, succeeded: int, failed: int, success_rate: float, common_success_patterns: array, common_failure_patterns: array}
     */
    public function getCapabilitySummary(DelegateeProfile $profile): array
    {
        $learnings = $this->getLearningsForProfile($profile);

        $totalTasks = 0;
        $succeeded = 0;
        $failed = 0;
        $allSuccessPatterns = [];
        $allFailurePatterns = [];

        foreach ($learnings as $learning) {
            $summary = $learning->outcome_summary_json ?? [];
            $totalTasks += ($summary['total_tasks'] ?? 0);
            $succeeded += ($summary['succeeded'] ?? 0);
            $failed += ($summary['failed'] ?? 0);

            $sp = $learning->success_patterns_json ?? [];
            foreach ($sp as $pattern) {
                $key = $pattern['pattern'] ?? 'unknown';
                $allSuccessPatterns[$key] = ($allSuccessPatterns[$key] ?? 0) + ($pattern['count'] ?? 1);
            }

            $fp = $learning->failure_patterns_json ?? [];
            foreach ($fp as $pattern) {
                $key = $pattern['pattern'] ?? 'unknown';
                $allFailurePatterns[$key] = ($allFailurePatterns[$key] ?? 0) + ($pattern['count'] ?? 1);
            }
        }

        arsort($allSuccessPatterns);
        arsort($allFailurePatterns);

        return [
            'total_tasks' => $totalTasks,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'success_rate' => $totalTasks > 0 ? round($succeeded / $totalTasks, 4) : 0.0,
            'common_success_patterns' => array_slice($allSuccessPatterns, 0, 10, true),
            'common_failure_patterns' => array_slice($allFailurePatterns, 0, 10, true),
        ];
    }

    /**
     * Build an outcome summary from the completed tasks.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, DelegationTask>  $tasks
     * @return array{total_tasks: int, succeeded: int, failed: int, cancelled: int, other: int}
     */
    private function buildOutcomeSummary(\Illuminate\Database\Eloquent\Collection $tasks): array
    {
        $succeeded = $tasks->where('status', DelegationTask::STATUS_SUCCEEDED)->count();
        $failed = $tasks->where('status', DelegationTask::STATUS_FAILED)->count();
        $cancelled = $tasks->where('status', DelegationTask::STATUS_CANCELLED)->count();

        return [
            'total_tasks' => $tasks->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'other' => $tasks->count() - $succeeded - $failed - $cancelled,
        ];
    }

    /**
     * Extract success patterns from completed tasks.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, DelegationTask>  $tasks
     * @return array<int, array{pattern: string, count: int}>
     */
    private function extractSuccessPatterns(\Illuminate\Database\Eloquent\Collection $tasks): array
    {
        $patterns = [];

        $succeededTasks = $tasks->where('status', DelegationTask::STATUS_SUCCEEDED);

        foreach ($succeededTasks as $task) {
            $contract = $task->contract_json ?? [];
            $capabilities = $contract['required_capabilities'] ?? [];

            foreach ($capabilities as $capability) {
                $patterns[$capability] = ($patterns[$capability] ?? 0) + 1;
            }
        }

        arsort($patterns);

        return array_map(
            fn (string $pattern, int $count) => ['pattern' => $pattern, 'count' => $count],
            array_keys($patterns),
            array_values($patterns)
        );
    }

    /**
     * Extract failure patterns from failed tasks.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, DelegationTask>  $tasks
     * @return array<int, array{pattern: string, count: int}>
     */
    private function extractFailurePatterns(\Illuminate\Database\Eloquent\Collection $tasks): array
    {
        $patterns = [];

        $failedTasks = $tasks->where('status', DelegationTask::STATUS_FAILED);

        foreach ($failedTasks as $task) {
            $errorCode = $task->error_code ?? 'unknown_error';
            $patterns[$errorCode] = ($patterns[$errorCode] ?? 0) + 1;
        }

        arsort($patterns);

        return array_map(
            fn (string $pattern, int $count) => ['pattern' => $pattern, 'count' => $count],
            array_keys($patterns),
            array_values($patterns)
        );
    }
}
