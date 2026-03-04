<?php

declare(strict_types=1);

namespace App\Repositories\Projection;

use App\Services\Telemetry\ActiveProjectionBuildStateService;
use App\Support\Telemetry\ProjectionTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

abstract class ActiveBuildScopedRepository
{
    public function __construct(
        protected readonly ActiveProjectionBuildStateService $buildStateService
    ) {}

    public function activeProjectionBuildId(): ?string
    {
        return $this->buildStateService->activeProjectionBuildId();
    }

    protected function query(string $table): Builder
    {
        return DB::table(ProjectionTable::qualified($table));
    }

    protected function scopeActiveBuild(Builder $query, string $projectionBuildColumn = 'projection_build_id'): Builder
    {
        $activeBuildId = $this->activeProjectionBuildId();
        if ($activeBuildId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($projectionBuildColumn, $activeBuildId);
    }
}
