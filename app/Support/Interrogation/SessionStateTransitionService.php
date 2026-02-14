<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationSession;
use Carbon\CarbonImmutable;

class SessionStateTransitionService
{
    /**
     * @param  array<int, string>  $fromStatuses
     * @param  array<string, mixed>  $attributes
     */
    public function transition(int $sessionId, array $fromStatuses, string $toStatus, array $attributes = []): bool
    {
        if ($fromStatuses === []) {
            return false;
        }

        $payload = array_merge($attributes, [
            'status' => $toStatus,
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);

        return InterrogationSession::query()
            ->whereKey($sessionId)
            ->whereIn('status', $fromStatuses)
            ->update($payload) === 1;
    }

    /**
     * @param  array<int, string>  $fromStatuses
     * @param  array<string, mixed>  $attributes
     */
    public function transitionPhase(int $sessionId, int $fromPhase, int $toPhase, string $toStatus, array $fromStatuses = [], array $attributes = []): bool
    {
        $allowedStatuses = $fromStatuses;

        if ($allowedStatuses === []) {
            $allowedStatuses = InterrogationSession::ACTIVE_STATUSES;
            $allowedStatuses[] = InterrogationSession::STATUS_SETUP;
            $allowedStatuses = array_values(array_unique($allowedStatuses));
        }

        $payload = array_merge($attributes, [
            'phase' => $toPhase,
            'status' => $toStatus,
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);

        return InterrogationSession::query()
            ->whereKey($sessionId)
            ->where('phase', $fromPhase)
            ->whereIn('status', $allowedStatuses)
            ->update($payload) === 1;
    }
}
