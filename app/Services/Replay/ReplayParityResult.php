<?php

declare(strict_types=1);

namespace App\Services\Replay;

use Carbon\CarbonInterface;

class ReplayParityResult
{
    public function __construct(
        public readonly bool $passed,
        /** @var list<array{source: string, expected: mixed, actual: mixed}> */
        public readonly array $discrepancies,
        public readonly CarbonInterface $checkedAt,
    ) {}
}
