<?php

declare(strict_types=1);

namespace App\Support\Compliance\DTOs;

readonly class GateResult
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $gate,
        public string $status,
        public ?string $reasonCode = null,
        public ?string $remediation = null,
        public array $evidence = []
    ) {}
}
