<?php

namespace App\Services\Replay;

use Carbon\CarbonImmutable;

class ReplayParityResult
{
    public function __construct(
        public readonly bool $passed,
        /** @var list<array{source: string, expected: mixed, actual: mixed}> */
        public readonly array $discrepancies,
        public readonly CarbonImmutable $checkedAt,
    ) {}
}
