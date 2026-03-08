<?php

declare(strict_types=1);

namespace App\Support\Connectors;

class ActionResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $data,
        public readonly array $headers = [],
        public readonly float $durationMs = 0,
        public readonly ?string $rawResponse = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
