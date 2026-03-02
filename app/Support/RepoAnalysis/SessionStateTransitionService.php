<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Models\RepoAnalysisSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class SessionStateTransitionService
{
    public const STATUS_SETUP = 'setup';

    public const STATUS_SNAPSHOTTING = 'snapshotting';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_REPORTING = 'reporting';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_FAILED = 'failed';

    /**
     * @var array<int, string>
     */
    private const ACTIVE_STATUS_BY_PHASE = [
        0 => self::STATUS_SETUP,
        1 => self::STATUS_SNAPSHOTTING,
        2 => self::STATUS_PLANNING,
        3 => self::STATUS_EXECUTING,
        4 => self::STATUS_VALIDATING,
        5 => self::STATUS_REPORTING,
        6 => self::STATUS_COMPLETED,
    ];

    /**
     * Allowed from-state matrix keyed by "phase:status" for the target state.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_FROM_MATRIX = [
        '1:snapshotting' => ['0:setup', '1:paused', '1:failed'],
        '2:planning' => ['1:snapshotting', '2:paused', '2:failed'],
        '3:executing' => ['2:planning', '3:paused', '3:failed'],
        '4:validating' => ['3:executing', '4:paused', '4:failed'],
        '5:reporting' => ['4:validating', '5:paused', '5:failed'],
        '6:completed' => ['5:reporting'],

        '1:paused' => ['1:snapshotting'],
        '2:paused' => ['2:planning'],
        '3:paused' => ['3:executing'],
        '4:paused' => ['4:validating'],
        '5:paused' => ['5:reporting'],

        '1:failed' => ['1:snapshotting'],
        '2:failed' => ['2:planning'],
        '3:failed' => ['3:executing'],
        '4:failed' => ['4:validating'],
        '5:failed' => ['5:reporting'],
    ];

    /**
     * Atomically transition a session to a target phase/status if current state is allowed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionTo(int $sessionId, int $toPhase, string $toStatus, array $attributes = []): bool
    {
        $targetState = $this->stateKey($toPhase, $toStatus);
        $allowedFrom = self::ALLOWED_FROM_MATRIX[$targetState] ?? [];

        if ($allowedFrom === []) {
            return false;
        }

        $payload = array_merge($attributes, [
            'phase' => $toPhase,
            'status' => $toStatus,
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);

        $updated = RepoAnalysisSession::query()
            ->whereKey($sessionId)
            ->where(function (Builder $query) use ($allowedFrom): void {
                foreach ($allowedFrom as $fromState) {
                    [$phase, $status] = $this->parseState($fromState);

                    $query->orWhere(function (Builder $stateQuery) use ($phase, $status): void {
                        $stateQuery
                            ->where('phase', $phase)
                            ->where('status', $status);
                    });
                }
            })
            ->update($payload);

        return $updated === 1;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function pause(int $sessionId, array $attributes = []): bool
    {
        $session = RepoAnalysisSession::query()->find($sessionId, ['id', 'phase', 'status']);
        if ($session === null) {
            return false;
        }

        $phase = (int) $session->phase;
        if (! isset(self::ACTIVE_STATUS_BY_PHASE[$phase]) || $phase === 0 || $phase === 6) {
            return false;
        }

        return $this->transitionTo($sessionId, $phase, self::STATUS_PAUSED, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resume(int $sessionId, array $attributes = []): bool
    {
        $session = RepoAnalysisSession::query()->find($sessionId, ['id', 'phase', 'status']);
        if ($session === null || (string) $session->status !== self::STATUS_PAUSED) {
            return false;
        }

        $phase = (int) $session->phase;
        $activeStatus = self::ACTIVE_STATUS_BY_PHASE[$phase] ?? null;
        if ($activeStatus === null || $activeStatus === self::STATUS_COMPLETED) {
            return false;
        }

        return $this->transitionTo($sessionId, $phase, $activeStatus, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function retry(int $sessionId, array $attributes = []): bool
    {
        $session = RepoAnalysisSession::query()->find($sessionId, ['id', 'phase', 'status']);
        if ($session === null || (string) $session->status !== self::STATUS_FAILED) {
            return false;
        }

        $phase = (int) $session->phase;
        $activeStatus = self::ACTIVE_STATUS_BY_PHASE[$phase] ?? null;
        if ($activeStatus === null || $activeStatus === self::STATUS_COMPLETED) {
            return false;
        }

        return $this->transitionTo($sessionId, $phase, $activeStatus, $attributes);
    }

    private function stateKey(int $phase, string $status): string
    {
        return $phase.':'.$status;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function parseState(string $state): array
    {
        [$phase, $status] = explode(':', $state, 2);

        return [(int) $phase, $status];
    }
}
