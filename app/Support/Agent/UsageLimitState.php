<?php

namespace App\Support\Agent;

use App\Models\AgentSystemState;
use Carbon\CarbonImmutable;

class UsageLimitState
{
    public function holdKeyForJob(int $jobId): string
    {
        return sprintf('job_rate_limit_hold_until:%d', $jobId);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function upsertHold(int $jobId, CarbonImmutable $holdUntilUtc, array $details = []): void
    {
        AgentSystemState::query()->updateOrCreate(
            ['key' => $this->holdKeyForJob($jobId)],
            [
                'value' => json_encode([
                    'hold_until' => $holdUntilUtc->setTimezone('UTC')->toIso8601String(),
                    'set_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    'details' => $details,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
                'updated_at' => CarbonImmutable::now('UTC'),
            ]
        );
    }

    /**
     * @return array{hold_until:CarbonImmutable,details:array<string,mixed>}|null
     */
    public function getActiveHold(int $jobId, ?CarbonImmutable $referenceUtc = null): ?array
    {
        $reference = ($referenceUtc ?? CarbonImmutable::now('UTC'))->setTimezone('UTC');

        $state = AgentSystemState::query()->find($this->holdKeyForJob($jobId));
        if ($state === null) {
            return null;
        }

        $value = is_string($state->value) ? trim($state->value) : '';
        if ($value === '') {
            $this->clearHold($jobId);

            return null;
        }

        $holdUntilRaw = $value;
        $details = [];

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                $holdUntilRaw = (string) ($decoded['hold_until'] ?? $holdUntilRaw);
                $details = is_array($decoded['details'] ?? null) ? $decoded['details'] : [];
            }
        } catch (\Throwable) {
            // Backward compatibility for plain ISO-8601 values.
        }

        try {
            $holdUntil = CarbonImmutable::parse($holdUntilRaw, 'UTC');
        } catch (\Throwable) {
            $this->clearHold($jobId);

            return null;
        }

        if ($holdUntil->lessThanOrEqualTo($reference)) {
            $this->clearHold($jobId);

            return null;
        }

        return [
            'hold_until' => $holdUntil,
            'details' => $details,
        ];
    }

    public function clearHold(int $jobId): void
    {
        AgentSystemState::query()->whereKey($this->holdKeyForJob($jobId))->delete();
    }
}
