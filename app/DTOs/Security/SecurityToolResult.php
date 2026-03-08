<?php

declare(strict_types=1);

namespace App\DTOs\Security;

use App\DTOs\Runtime\ToolResult;
use App\Enums\Security\ContentTrustLevel;
use App\Enums\Security\InjectionAction;

readonly class SecurityToolResult
{
    public function __construct(
        public ToolResult $original,
        public ContentTrustLevel $contentTrustLevel,
        public float $injectionScore,
        public InjectionAction $injectionAction,
        public bool $sanitized,
        public int $originalTokenCount,
        public bool $truncated,
        public string $sourceIdentifier,
    ) {}

    public static function fromToolResult(
        ToolResult $result,
        ContentTrustLevel $trustLevel,
        string $source,
    ): self {
        return new self(
            original: $result,
            contentTrustLevel: $trustLevel,
            injectionScore: 0.0,
            injectionAction: InjectionAction::Log,
            sanitized: false,
            originalTokenCount: 0,
            truncated: false,
            sourceIdentifier: $source,
        );
    }

    public function withInjectionResult(float $score, InjectionAction $action): self
    {
        return new self(
            original: $this->original,
            contentTrustLevel: $this->contentTrustLevel,
            injectionScore: $score,
            injectionAction: $action,
            sanitized: $this->sanitized,
            originalTokenCount: $this->originalTokenCount,
            truncated: $this->truncated,
            sourceIdentifier: $this->sourceIdentifier,
        );
    }

    public function withSanitization(bool $sanitized, int $originalTokenCount, bool $truncated): self
    {
        return new self(
            original: $this->original,
            contentTrustLevel: $this->contentTrustLevel,
            injectionScore: $this->injectionScore,
            injectionAction: $this->injectionAction,
            sanitized: $sanitized,
            originalTokenCount: $originalTokenCount,
            truncated: $truncated,
            sourceIdentifier: $this->sourceIdentifier,
        );
    }

    public function success(): bool
    {
        return $this->original->success;
    }

    public function data(): mixed
    {
        return $this->original->data;
    }

    public function error(): ?string
    {
        return $this->original->error;
    }

    public function durationMs(): int
    {
        return $this->original->durationMs;
    }

    public function artifacts(): array
    {
        return $this->original->artifacts;
    }
}
