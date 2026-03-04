<?php

declare(strict_types=1);

namespace App\Support\Telemetry;

use Illuminate\Support\Facades\DB;

final class ProjectionTable
{
    private const PROJECTION_SCHEMA = 'agent_projection';

    public static function qualified(string $table): string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $table;
        }

        return self::PROJECTION_SCHEMA.'.'.$table;
    }
}
