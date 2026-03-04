<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Support\Time\AgentClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class ActiveBuildFreshnessService
{
    private const ACTIVE_BUILD_AGE_SECONDS_METRIC_KEY = 'agent:telemetry:active_build_age_seconds';

    public function __construct(
        private readonly ActiveProjectionBuildStateService $buildStateService,
        private readonly AgentClock $clock,
    ) {}

    /**
     * @return array{
     *     active_build_id:?string,
     *     active_build_activated_at:?string,
     *     active_build_age_seconds:?int,
     *     active_build_is_stale:?bool,
     *     stale_after_seconds:int
     * }
     */
    public function snapshot(): array
    {
        $activeBuildId = $this->buildStateService->activeProjectionBuildId();
        $activatedAtIso8601 = $this->buildStateService->activatedAtIso8601();
        $staleAfterSeconds = $this->staleAfterSeconds();

        if ($activeBuildId === null || $activatedAtIso8601 === null) {
            $this->emitGauge(null);

            return [
                'active_build_id' => $activeBuildId,
                'active_build_activated_at' => $activatedAtIso8601,
                'active_build_age_seconds' => null,
                'active_build_is_stale' => null,
                'stale_after_seconds' => $staleAfterSeconds,
            ];
        }

        $activatedAt = CarbonImmutable::parse($activatedAtIso8601, 'UTC')->utc();
        $ageSeconds = max(0, $this->clock->nowUtc()->getTimestamp() - $activatedAt->getTimestamp());
        $isStale = $ageSeconds > $staleAfterSeconds;

        $this->emitGauge($ageSeconds);

        return [
            'active_build_id' => $activeBuildId,
            'active_build_activated_at' => $activatedAt->toIso8601String(),
            'active_build_age_seconds' => $ageSeconds,
            'active_build_is_stale' => $isStale,
            'stale_after_seconds' => $staleAfterSeconds,
        ];
    }

    private function staleAfterSeconds(): int
    {
        return max(1, (int) config('agent.projections.stale_after_seconds', 900));
    }

    private function emitGauge(?int $ageSeconds): void
    {
        if ($ageSeconds === null) {
            Cache::forget(self::ACTIVE_BUILD_AGE_SECONDS_METRIC_KEY);

            return;
        }

        Cache::forever(self::ACTIVE_BUILD_AGE_SECONDS_METRIC_KEY, $ageSeconds);
    }
}
