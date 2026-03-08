<?php

declare(strict_types=1);

namespace App\Messenger\ChatAction;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\User;

class ChatActionContext
{
    public function __construct(
        private User $user,
        private array $parameters = [],
        private string $action = '',
        private ?AgentJob $targetJob = null,
        private bool $confirmed = false,
        private ?AgentJobRun $targetRun = null,
    ) {}

    public function getAuthenticatedUser(): User
    {
        return $this->user;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getTargetJob(): ?AgentJob
    {
        return $this->targetJob;
    }

    public function getTargetRun(): ?AgentJobRun
    {
        return $this->targetRun;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function withConfirmation(bool $confirmed): self
    {
        return new self(
            user: $this->user,
            parameters: $this->parameters,
            action: $this->action,
            targetJob: $this->targetJob,
            confirmed: $confirmed,
            targetRun: $this->targetRun,
        );
    }
}
