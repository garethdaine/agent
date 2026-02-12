<?php

namespace App\Support\Agent;

use Carbon\CarbonImmutable;

class Duration
{
    public static function millisecondsBetween(mixed $startedAt, CarbonImmutable $finishedAt): int
    {
        if ($startedAt === null) {
            return 0;
        }

        try {
            $start = CarbonImmutable::parse($startedAt, 'UTC');
        } catch (\Throwable) {
            return 0;
        }

        return (int) max(0, round((float) $start->diffInMilliseconds($finishedAt)));
    }
}
