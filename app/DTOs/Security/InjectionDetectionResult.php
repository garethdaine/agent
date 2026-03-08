<?php

declare(strict_types=1);

namespace App\DTOs\Security;

use App\Enums\Security\InjectionAction;

readonly class InjectionDetectionResult
{
    public function __construct(
        public float $score,
        public array $matchedPatterns,
        public InjectionAction $recommendedAction,
        public int $scanDurationMs,
    ) {}
}
