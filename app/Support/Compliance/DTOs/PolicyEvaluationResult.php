<?php

declare(strict_types=1);

namespace App\Support\Compliance\DTOs;

use App\Enums\TaskCategory;

readonly class PolicyEvaluationResult
{
    /**
     * @param  array<GateResult>  $gates
     * @param  array<string, mixed>  $metadataPatch
     */
    public function __construct(
        public string $status,
        public string $complexity,
        public TaskCategory $taskCategory,
        public bool $planRequired,
        public bool $verificationRequired,
        public bool $eleganceRequired,
        public array $gates,
        public array $metadataPatch = []
    ) {}

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }
}
