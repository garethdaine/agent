<?php

namespace App\Support\Compliance\DTOs;

readonly class CompletionGateResult
{
    /**
     * @param  array<GateResult>  $gates
     */
    public function __construct(
        public bool $canComplete,
        public string $status,
        public array $gates,
        public ?string $blockReason = null,
        public ?string $remediation = null
    ) {}
}
