<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Models\AgentJobRun;
use Carbon\CarbonImmutable;

class RunStateTransitionService
{
    /**
     * @param  array<int, string>  $fromStatuses
     * @param  array<string, mixed>  $attributes
     */
    public function transition(int $runId, array $fromStatuses, string $toStatus, array $attributes = []): bool
    {
        if ($fromStatuses === []) {
            return false;
        }

        $payload = array_merge($attributes, [
            'status' => $toStatus,
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);

        $updated = AgentJobRun::query()
            ->whereKey($runId)
            ->whereIn('status', $fromStatuses)
            ->update($payload);

        return $updated === 1;
    }
}
