<?php

declare(strict_types=1);

namespace App\DTOs\Security;

readonly class FirewallDecision
{
    public function __construct(
        public bool $allowed,
        public bool $requiresEscalation,
        public ?string $reason,
        public ?string $taintSource,
    ) {}
}
