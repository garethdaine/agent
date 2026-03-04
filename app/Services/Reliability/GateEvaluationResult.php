<?php

declare(strict_types=1);

namespace App\Services\Reliability;

final readonly class GateEvaluationResult
{
    public function __construct(
        public string $gateState,
        public ?string $reasonCode,
        public bool $shouldAutoPause,
    ) {}
}
