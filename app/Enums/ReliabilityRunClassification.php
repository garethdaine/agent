<?php

declare(strict_types=1);

namespace App\Enums;

enum ReliabilityRunClassification: string
{
    case SUCCESS = 'success';
    case ASSISTED = 'assisted';
    case DEGRADED = 'degraded';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';

    public function weight(): float
    {
        return match ($this) {
            self::SUCCESS => 1.0,
            self::ASSISTED => 0.7,
            self::DEGRADED => 0.5,
            self::FAILED => 0.0,
            self::SKIPPED => 0.0,
        };
    }

    public function countsTowardReliability(): bool
    {
        return $this !== self::SKIPPED;
    }
}
