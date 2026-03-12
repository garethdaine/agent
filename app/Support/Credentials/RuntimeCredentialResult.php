<?php

declare(strict_types=1);

namespace App\Support\Credentials;

readonly class RuntimeCredentialResult
{
    public function __construct(
        public ApprovalDecision $decision,
        public ?array $credentials = null,
        public ?string $credentialId = null,
        public ?string $credentialType = null,
        public ?string $reason = null,
    ) {}

    public function isAllowed(): bool
    {
        return $this->decision === ApprovalDecision::Allowed;
    }

    public function needsConfirmation(): bool
    {
        return $this->decision === ApprovalDecision::NeedsConfirmation;
    }
}
