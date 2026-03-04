<?php

declare(strict_types=1);

namespace App\Support\Time;

use Carbon\CarbonImmutable;

final class AgentClock
{
    public function nowUtc(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
