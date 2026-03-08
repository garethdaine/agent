<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Support\Telemetry\ProjectionTable;
use Illuminate\Support\Facades\DB;

final class ActiveProjectionBuildStateService
{
    public function activeProjectionBuildId(): ?string
    {
        $state = $this->currentState();

        $activeBuildId = $state?->active_projection_build_id;
        if (! is_string($activeBuildId) || trim($activeBuildId) === '') {
            return null;
        }

        return $activeBuildId;
    }

    public function rebuildingBuildId(): ?string
    {
        $state = $this->currentState();

        $rebuildingBuildId = $state?->rebuilding_build_id;
        if (! is_string($rebuildingBuildId) || trim($rebuildingBuildId) === '') {
            return null;
        }

        return $rebuildingBuildId;
    }

    public function activatedAtIso8601(): ?string
    {
        $state = $this->currentState();
        if (! isset($state->activated_at) || $state->activated_at === null) { // @phpstan-ignore identical.alwaysFalse
            return null;
        }

        return (string) $state->activated_at;
    }

    private function currentState(): ?object
    {
        return DB::table(ProjectionTable::qualified('telemetry_projection_build_state'))
            ->where('id', 1)
            ->first();
    }
}
