<?php

declare(strict_types=1);

namespace App\Support\Delegation;

use App\Models\AgentJobRun;
use App\Models\DelegateeProfile;
use App\Models\TrustScoreHistory;
use App\Models\User;
use App\Support\Delegation\DTOs\StarMetrics;
use App\Support\Delegation\DTOs\TrustScore;
use Illuminate\Support\Facades\Cache;

class TrustScoreCalculator
{
    public function calculate(string $runnerType, ?int $jobId = null): TrustScore
    {
        $cacheKey = "trust_score:{$runnerType}:".($jobId ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($runnerType, $jobId) {
            return $this->computeScore($runnerType, $jobId);
        });
    }

    /**
     * Recalculate trust score for a delegatee profile and persist history.
     */
    public function recalculateForProfile(DelegateeProfile $profile, string $reason = 'scheduled_recalculation'): TrustScore
    {
        $trustScore = $this->calculate($profile->runner_type);

        // Persist to profile
        $profile->update([
            'trust_score' => $trustScore->score,
            'trust_updated_at' => now(),
        ]);

        // Record history
        TrustScoreHistory::create([
            'delegatee_profile_id' => $profile->id,
            'score' => $trustScore->score,
            'components_json' => $trustScore->components,
            'calculated_at' => now(),
            'reason' => $reason,
        ]);

        // Bust cache so next calculate() gets fresh data
        $cacheKey = "trust_score:{$profile->runner_type}:all";
        Cache::forget($cacheKey);

        return $trustScore;
    }

    /**
     * Calculate StarMetrics for a specific user based on their job run history.
     */
    public function calculateForUser(User $user): StarMetrics
    {
        $windowSize = config('agent.trust.window_size', 50);

        $runs = AgentJobRun::query()
            ->join('agent_jobs', 'agent_job_runs.agent_job_id', '=', 'agent_jobs.id')
            ->where('agent_jobs.user_id', $user->id)
            ->orderByDesc('agent_job_runs.created_at')
            ->limit($windowSize)
            ->get(['agent_job_runs.*']);

        return $this->aggregateMetricsWithStarComponents($runs);
    }

    /**
     * Aggregate metrics including STAR component correctness from run metadata.
     */
    private function aggregateMetricsWithStarComponents($runs): StarMetrics
    {
        $total = $runs->count();

        if ($total === 0) {
            return new StarMetrics(0, 0, 0, 0, 0, 0, 0, [], 0);
        }

        $starCompleted = 0;
        $successful = 0;
        $retrySuccessful = 0;
        $retryAttempted = 0;
        $failureModes = ['type_1' => 0, 'type_2' => 0, 'type_3' => 0];

        // STAR component tracking
        $situationCorrectCount = 0;
        $taskCorrectCount = 0;
        $actionCorrectCount = 0;
        $resultCorrectCount = 0;
        $runsWithStarData = 0;

        foreach ($runs as $run) {
            $metadata = $run->metadata_json ?? [];
            $summary = $metadata['reasoning_summary'] ?? [];

            if ($summary['all_completed'] ?? false) {
                $starCompleted++;
            }

            if ($run->status === AgentJobRun::STATUS_SUCCEEDED) {
                $successful++;
                if (isset($metadata['retry_of_run_id'])) {
                    $retrySuccessful++;
                }
            }

            if (isset($metadata['retry_of_run_id'])) {
                $retryAttempted++;
            }

            if (isset($metadata['failure_mode_hint']['type'])) {
                $type = 'type_'.$metadata['failure_mode_hint']['type'];
                $failureModes[$type] = ($failureModes[$type] ?? 0) + 1;
            }

            // Extract STAR component correctness from metadata
            if (isset($summary['situation_correct']) || isset($summary['task_correct'])
                || isset($summary['action_correct']) || isset($summary['result_correct'])) {
                $runsWithStarData++;

                if ($summary['situation_correct'] ?? false) {
                    $situationCorrectCount++;
                }
                if ($summary['task_correct'] ?? false) {
                    $taskCorrectCount++;
                }
                if ($summary['action_correct'] ?? false) {
                    $actionCorrectCount++;
                }
                if ($summary['result_correct'] ?? false) {
                    $resultCorrectCount++;
                }
            }
        }

        // Calculate STAR rates from runs that have STAR data, or 0 if none
        $situationCorrectRate = $runsWithStarData > 0 ? $situationCorrectCount / $runsWithStarData : 0.0;
        $taskCorrectRate = $runsWithStarData > 0 ? $taskCorrectCount / $runsWithStarData : 0.0;
        $actionCorrectRate = $runsWithStarData > 0 ? $actionCorrectCount / $runsWithStarData : 0.0;
        $resultCorrectRate = $runsWithStarData > 0 ? $resultCorrectCount / $runsWithStarData : 0.0;

        return new StarMetrics(
            starCompletionRate: $starCompleted / $total,
            situationCorrectRate: $situationCorrectRate,
            taskCorrectRate: $taskCorrectRate,
            actionCorrectRate: $actionCorrectRate,
            resultCorrectRate: $resultCorrectRate,
            firstPassSuccessRate: ($successful - $retrySuccessful) / max(1, $total - $retryAttempted),
            recoveryRate: $retryAttempted > 0 ? $retrySuccessful / $retryAttempted : 0,
            failureModeDistribution: array_map(fn ($c) => $c / max(1, $total), $failureModes),
            sampleSize: $total
        );
    }

    public function getMetrics(string $runnerType, ?int $jobId = null): StarMetrics
    {
        $windowSize = config('agent.trust.window_size', 50);

        $query = AgentJobRun::query()
            ->join('agent_jobs', 'agent_job_runs.agent_job_id', '=', 'agent_jobs.id')
            ->where('agent_jobs.runner_type', $runnerType)
            ->whereNotNull('agent_job_runs.metadata_json->reasoning_summary')
            ->orderByDesc('agent_job_runs.created_at')
            ->limit($windowSize);

        if ($jobId !== null) {
            $query->where('agent_job_runs.agent_job_id', $jobId);
        }

        $runs = $query->get(['agent_job_runs.*']);

        return $this->aggregateMetrics($runs);
    }

    private function computeScore(string $runnerType, ?int $jobId): TrustScore
    {
        $minJobRuns = config('agent.trust.min_job_runs', 10);
        $defaultScore = config('agent.trust.default_score', 0.5);

        // Try job-level first
        if ($jobId !== null) {
            $jobMetrics = $this->getMetricsForScope($runnerType, $jobId);
            if ($jobMetrics->sampleSize >= $minJobRuns) {
                return $this->buildTrustScore($jobMetrics, 'job');
            }
        }

        // Fall back to runner-type level
        $runnerMetrics = $this->getMetricsForScope($runnerType, null);
        if ($runnerMetrics->sampleSize > 0) {
            return $this->buildTrustScore($runnerMetrics, 'runner_type');
        }

        // Cold start default
        return new TrustScore(
            score: $defaultScore,
            confidence: 'low',
            components: [],
            sampleSize: 0,
            source: 'default'
        );
    }

    private function getMetricsForScope(string $runnerType, ?int $jobId): StarMetrics
    {
        return $this->getMetrics($runnerType, $jobId);
    }

    private function aggregateMetrics($runs): StarMetrics
    {
        // Delegate to the unified implementation that handles STAR component extraction
        return $this->aggregateMetricsWithStarComponents($runs);
    }

    private function buildTrustScore(StarMetrics $metrics, string $source): TrustScore
    {
        // Score formula from spec
        $baseScore =
            $metrics->starCompletionRate * 0.15 +
            $metrics->taskCorrectRate * 0.35 +
            $metrics->firstPassSuccessRate * 0.30 +
            $metrics->recoveryRate * 0.20;

        $penalty = ($metrics->failureModeDistribution['type_1'] ?? 0) * 0.15;
        $finalScore = max(0, min(1, $baseScore - $penalty));

        $confidence = match (true) {
            $metrics->sampleSize >= 50 => 'high',
            $metrics->sampleSize >= 20 => 'medium',
            default => 'low',
        };

        return new TrustScore(
            score: round($finalScore, 2),
            confidence: $confidence,
            components: [
                'star_completion' => $metrics->starCompletionRate,
                'task_correct' => $metrics->taskCorrectRate,
                'first_pass_success' => $metrics->firstPassSuccessRate,
                'recovery' => $metrics->recoveryRate,
            ],
            sampleSize: $metrics->sampleSize,
            source: $source
        );
    }
}
