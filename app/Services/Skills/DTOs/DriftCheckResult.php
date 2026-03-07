<?php

declare(strict_types=1);

namespace App\Services\Skills\DTOs;

final readonly class DriftCheckResult
{
    public function __construct(
        public string $skillId,
        public string $skillName,
        public bool $hasDrift,
        public array $changedFiles = [],
        public array $addedFiles = [],
        public array $missingFiles = [],
    ) {}
}
