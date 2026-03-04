<?php

declare(strict_types=1);

namespace App\Services\Reliability;

use App\Enums\ReliabilityRunClassification;

final readonly class RunClassificationResult
{
    public function __construct(
        public ReliabilityRunClassification $classification,
        public float $weight,
        public bool $hardFail,
        public ?string $reasonCode,
    ) {}
}
