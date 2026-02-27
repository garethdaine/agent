<?php

namespace App\Support\Delegation;

use App\Models\AgentJobRun;
use App\Support\Delegation\DTOs\StarMetrics;
use App\Support\Delegation\DTOs\TrustScore;
use Illuminate\Support\Facades\Cache;

class TrustScoreCalculator
{
    public function calculate(string $runnerType, ?int $jobId = null): TrustScore
    {
        $cacheKey = "trust_score:{$runnerType}:" . ($jobId ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($runnerType, $jobId) {
            return $this->computeScore($runnerType, $jobId);
        });
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
        $total = $runs->count();

        if ($total === 0) {
            return new StarMetrics(0, 0, 0, 0, 0, 0, 0, [], 0);
        }

        $starCompleted = 0;
        $successful = 0;
        $retrySuccessful = 0;
        $retryAttempted = 0;
        $failureModes = ['type_1' => 0, 'type_2' => 0, 'type_3' => 0];

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
                $type = 'type_' . $metadata['failure_mode_hint']['type'];
                $failureModes[$type] = ($failureModes[$type] ?? 0) + 1;
            }
        }

        return new StarMetrics(
            starCompletionRate: $starCompleted / $total,
            situationCorrectRate: 0.8, // Placeholder - requires deeper analysis
            taskCorrectRate: 0.8,
            actionCorrectRate: 0.8,
            resultCorrectRate: 0.8,
            firstPassSuccessRate: ($successful - $retrySuccessful) / max(1, $total - $retryAttempted),
            recoveryRate: $retryAttempted > 0 ? $retrySuccessful / $retryAttempted : 0,
            failureModeDistribution: array_map(fn ($c) => $c / max(1, $total), $failureModes),
            sampleSize: $total
        );
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
