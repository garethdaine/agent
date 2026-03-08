<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Support\Agent\AuditLogger;
use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ProjectionBuildManager
{
    private const REBUILD_CONFLICT_METRIC_KEY = 'agent:telemetry:projection_rebuild_conflicts';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Auto-maintain projections: activate parity-passed builds and start rebuilds when stale.
     */
    public function autoMaintain(
        ActiveBuildFreshnessService $freshnessService,
        ActiveProjectionBuildStateService $buildStateService,
    ): void {
        // 1. Auto-activate any build that has reached parity_passed status.
        $parityPassedBuild = DB::table($this->buildsTable())
            ->where('status', 'parity_passed')
            ->orderBy('created_at')
            ->first(['id']);

        if ($parityPassedBuild !== null) {
            $result = $this->activateBuild((string) $parityPassedBuild->id);
            Log::info('telemetry.projection_auto_maintain.activate', [
                'build_id' => (string) $parityPassedBuild->id,
                'activated' => $result->isActivated(),
            ]);

            return;
        }

        // 2. If active build is stale and no rebuild in progress, start one.
        $snapshot = $freshnessService->snapshot();
        $isStale = $snapshot['active_build_is_stale'] ?? false;
        $rebuildingBuildId = $buildStateService->rebuildingBuildId();

        if ($isStale && $rebuildingBuildId === null) {
            $result = $this->startRebuild();
            Log::info('telemetry.projection_auto_maintain.rebuild', [
                'outcome' => $result->isConflict() ? 'conflict' : 'started',
                'build_id' => $result->buildId(),
            ]);
        }
    }

    public function startRebuild(): ProjectionRebuildStartResult
    {
        try {
            return DB::transaction(function (): ProjectionRebuildStartResult {
                $state = $this->loadStateForUpdate();
                $activeBuildId = $this->normalizeId($state->active_projection_build_id ?? null);

                $existingRebuild = $this->findExistingRebuildingBuildForUpdate();
                if ($existingRebuild !== null) {
                    $rebuildingBuildId = $this->normalizeId($existingRebuild->id ?? null);
                    $this->syncRebuildingPointer($rebuildingBuildId);

                    $result = ProjectionRebuildStartResult::conflict($activeBuildId, $rebuildingBuildId);
                    $this->recordConflictObservability($result);

                    return $result;
                }

                $buildId = (string) Str::uuid();
                $now = now('UTC');

                DB::table($this->buildsTable())->insert([
                    'id' => $buildId,
                    'status' => 'rebuilding',
                    'activated_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table($this->buildStateTable())->updateOrInsert(
                    ['id' => 1],
                    [
                        'active_projection_build_id' => $activeBuildId,
                        'rebuilding_build_id' => $buildId,
                        'updated_at' => $now,
                    ]
                );

                return ProjectionRebuildStartResult::started($buildId, $activeBuildId);
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isRebuildingUniquenessViolation($exception)) {
                throw $exception;
            }

            $result = $this->resolveConflictFromCurrentState();
            $this->recordConflictObservability($result);

            return $result;
        }
    }

    /**
     * @return array{
     *     id:string,
     *     status:string,
     *     activated_at:?string,
     *     created_at:?string,
     *     updated_at:?string
     * }|null
     */
    public function findBuild(string $buildId): ?array
    {
        $row = DB::table($this->buildsTable())
            ->where('id', $buildId)
            ->first([
                'id',
                'status',
                'activated_at',
                'created_at',
                'updated_at',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'status' => $this->normalizeStatus($row->status ?? null),
            'activated_at' => isset($row->activated_at) ? (string) $row->activated_at : null,
            'created_at' => isset($row->created_at) ? (string) $row->created_at : null,
            'updated_at' => isset($row->updated_at) ? (string) $row->updated_at : null,
        ];
    }

    public function activateBuild(string $buildId): ProjectionBuildActivationResult
    {
        return DB::transaction(function () use ($buildId): ProjectionBuildActivationResult {
            $state = $this->loadStateForUpdate();
            $activeBuildId = $this->normalizeId($state->active_projection_build_id ?? null);
            $rebuildingBuildId = $this->normalizeId($state->rebuilding_build_id ?? null);

            $build = DB::table($this->buildsTable())
                ->where('id', $buildId)
                ->lockForUpdate()
                ->first(['id', 'status']);

            if ($build === null) {
                return ProjectionBuildActivationResult::notFound(
                    buildId: $buildId,
                    activeBuildId: $activeBuildId,
                    rebuildingBuildId: $rebuildingBuildId,
                );
            }

            $status = $this->normalizeStatus($build->status ?? null);
            if (! in_array($status, ['parity_passed', 'active'], true)) {
                return ProjectionBuildActivationResult::parityRequired(
                    buildId: $buildId,
                    currentStatus: $status,
                    activeBuildId: $activeBuildId,
                    rebuildingBuildId: $rebuildingBuildId,
                );
            }

            $now = now('UTC');

            if ($activeBuildId !== null && $activeBuildId !== $buildId) {
                DB::table($this->buildsTable())
                    ->where('id', $activeBuildId)
                    ->update([
                        'status' => 'historical',
                        'updated_at' => $now,
                    ]);
            }

            DB::table($this->buildsTable())
                ->where('id', $buildId)
                ->update([
                    'status' => 'active',
                    'activated_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table($this->buildStateTable())->updateOrInsert(
                ['id' => 1],
                [
                    'active_projection_build_id' => $buildId,
                    'rebuilding_build_id' => null,
                    'activated_at' => $now,
                    'updated_at' => $now,
                ]
            );

            return ProjectionBuildActivationResult::activated(
                buildId: $buildId,
                previousActiveBuildId: $activeBuildId,
                activatedAtIso8601: $now->toIso8601String(),
            );
        }, 3);
    }

    private function resolveConflictFromCurrentState(): ProjectionRebuildStartResult
    {
        $state = DB::table($this->buildStateTable())
            ->where('id', 1)
            ->first();

        $activeBuildId = $this->normalizeId($state->active_projection_build_id ?? null);
        $rebuildingBuildId = $this->normalizeId($state->rebuilding_build_id ?? null);

        if ($rebuildingBuildId === null) {
            $rebuildingBuildId = $this->normalizeId(
                DB::table($this->buildsTable())
                    ->where('status', 'rebuilding')
                    ->orderByDesc('created_at')
                    ->value('id')
            );
        }

        return ProjectionRebuildStartResult::conflict($activeBuildId, $rebuildingBuildId);
    }

    private function loadStateForUpdate(): ?object
    {
        DB::table($this->buildStateTable())->insertOrIgnore([
            'id' => 1,
            'active_projection_build_id' => null,
            'rebuilding_build_id' => null,
            'activated_at' => null,
            'updated_at' => now('UTC'),
        ]);

        return DB::table($this->buildStateTable())
            ->where('id', 1)
            ->lockForUpdate()
            ->first();
    }

    private function syncRebuildingPointer(?string $rebuildingBuildId): void
    {
        DB::table($this->buildStateTable())
            ->where('id', 1)
            ->update([
                'rebuilding_build_id' => $rebuildingBuildId,
                'updated_at' => now('UTC'),
            ]);
    }

    private function findExistingRebuildingBuildForUpdate(): ?object
    {
        return DB::table($this->buildsTable())
            ->where('status', 'rebuilding')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->first(['id']);
    }

    private function isRebuildingUniquenessViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        if ($sqlState === '23505') {
            return str_contains(strtolower($exception->getMessage()), 'telemetry_projection_builds_one_rebuilding_idx');
        }

        return str_contains(strtolower($exception->getMessage()), 'telemetry_projection_builds_one_rebuilding_idx');
    }

    private function recordConflictObservability(ProjectionRebuildStartResult $result): void
    {
        Log::warning('telemetry.projection_rebuild.conflict', [
            'active_build_id' => $result->activeBuildId(),
            'rebuilding_build_id' => $result->rebuildingBuildId(),
            'message' => $result->message(),
        ]);

        Cache::increment(self::REBUILD_CONFLICT_METRIC_KEY);

        try {
            $this->auditLogger->recordSystemAction(
                action: 'projection_rebuild_conflict',
                targetType: 'telemetry_projection_build',
                targetId: (string) ($result->rebuildingBuildId() ?? 'unknown'),
                changedFields: ['active_build_id', 'rebuilding_build_id'],
                before: null,
                after: [
                    'active_build_id' => $result->activeBuildId(),
                    'rebuilding_build_id' => $result->rebuildingBuildId(),
                    'message' => $result->message(),
                ],
                outcome: 'conflict',
                errorCode: 'PROJECTION_REBUILD_IN_PROGRESS',
                errorMessage: $result->message(),
            );
        } catch (\Throwable $exception) {
            Log::warning('telemetry.projection_rebuild.conflict_audit_failed', [
                'active_build_id' => $result->activeBuildId(),
                'rebuilding_build_id' => $result->rebuildingBuildId(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildStateTable(): string
    {
        return ProjectionTable::qualified('telemetry_projection_build_state');
    }

    private function buildsTable(): string
    {
        return ProjectionTable::qualified('telemetry_projection_builds');
    }

    private function normalizeId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeStatus(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
