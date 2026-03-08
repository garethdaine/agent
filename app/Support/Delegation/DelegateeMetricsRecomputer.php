<?php

declare(strict_types=1);

namespace App\Support\Delegation;

use App\Models\DelegateeMetric;
use App\Models\DelegateeProfile;
use App\Models\DelegationAttempt;
use Illuminate\Support\Facades\Cache;

/**
 * Computes and maintains sliding window metrics for delegatee profiles.
 *
 * Metrics are computed for two time windows:
 * - 24-hour window: Used for assignment ranking decisions
 * - 7-day window: Used for longer-term performance analysis
 *
 * Can be triggered by:
 * - Scheduled execution every 15 minutes
 * - Event-triggered recomputation (throttled to once per 60 seconds per profile)
 */
class DelegateeMetricsRecomputer
{
    /**
     * Recompute metrics for a specific delegatee profile.
     */
    public function recompute(DelegateeProfile $profile): void
    {
        $window24h = $this->computeWindowMetrics($profile, 24);
        $window7d = $this->computeWindowMetrics($profile, 168); // 7 days = 168 hours

        DelegateeMetric::updateOrCreate(
            ['delegatee_profile_id' => $profile->id],
            [
                'window_24h_json' => $window24h,
                'window_7d_json' => $window7d,
                'last_recomputed_at' => now(),
            ]
        );
    }

    /**
     * Recompute metrics for all active delegatee profiles.
     */
    public function recomputeAll(): void
    {
        DelegateeProfile::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->chunk(100, function ($profiles) {
                foreach ($profiles as $profile) {
                    $this->recompute($profile);
                }
            });
    }

    /**
     * Recompute metrics if not throttled.
     *
     * Used for event-triggered recomputation to prevent excessive
     * database load during high-volume delegation activity.
     *
     * @return bool True if recomputation was performed, false if throttled
     */
    public function recomputeIfNotThrottled(DelegateeProfile $profile): bool
    {
        $throttleSeconds = config('delegation.metrics_event_throttle_seconds', 60);
        $cacheKey = "delegatee_metrics_recompute:{$profile->id}";

        // Use cache lock to prevent concurrent recomputation
        if (Cache::has($cacheKey)) {
            return false;
        }

        // Set throttle lock
        Cache::put($cacheKey, true, $throttleSeconds);

        $this->recompute($profile);

        return true;
    }

    /**
     * Compute metrics for a specific time window.
     *
     * @param  DelegateeProfile  $profile  The profile to compute metrics for
     * @param  int  $hours  The time window in hours
     */
    private function computeWindowMetrics(DelegateeProfile $profile, int $hours): array
    {
        $cutoff = now()->subHours($hours);

        $attempts = DelegationAttempt::query()
            ->where('delegatee_profile_id', $profile->id)
            ->whereNotNull('finished_at')
            ->where('finished_at', '>=', $cutoff)
            ->get();

        $totalAttempts = $attempts->count();
        $successfulAttempts = $attempts
            ->where('status', DelegationAttempt::STATUS_SUCCEEDED)
            ->count();

        $successRate = $totalAttempts > 0
            ? round($successfulAttempts / $totalAttempts, 4)
            : 0.0;

        $avgDurationMs = $totalAttempts > 0
            ? (int) round($attempts->avg('duration_ms'))
            : 0;

        return [
            'total_attempts' => $totalAttempts,
            'successful_attempts' => $successfulAttempts,
            'failed_attempts' => $totalAttempts - $successfulAttempts,
            'success_rate' => $successRate,
            'avg_duration_ms' => $avgDurationMs,
            'window_hours' => $hours,
            'computed_at' => now()->toISOString(),
        ];
    }
}
