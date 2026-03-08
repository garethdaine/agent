<?php

declare(strict_types=1);

namespace App\DTOs\Connectors;

class ConnectionTestResult
{
    public function __construct(
        public readonly bool $connected,
        public readonly ?float $latencyMs = null,
        public readonly ?string $error = null,
    ) {}

    public static function healthy(float $latencyMs): self
    {
        return new self(connected: true, latencyMs: $latencyMs);
    }

    public static function failed(string $error, ?float $latencyMs = null): self
    {
        return new self(connected: false, latencyMs: $latencyMs, error: $error);
    }
}
