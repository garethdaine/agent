<?php

declare(strict_types=1);

namespace App\Services\Security;

use Carbon\CarbonImmutable;

class TurnSecurityContext
{
    private bool $tainted = false;

    private ?string $taintSource = null;

    private ?CarbonImmutable $taintedAt = null;

    private int $toolCallCount = 0;

    private int $untrustedResultCount = 0;

    public function markTainted(string $source): void
    {
        if ($this->tainted) {
            return;
        }

        $this->tainted = true;
        $this->taintSource = $source;
        $this->taintedAt = CarbonImmutable::now();
    }

    public function isTainted(): bool
    {
        return $this->tainted;
    }

    public function getTaintSource(): ?string
    {
        return $this->taintSource;
    }

    public function getTaintedAt(): ?CarbonImmutable
    {
        return $this->taintedAt;
    }

    public function incrementToolCall(): void
    {
        $this->toolCallCount++;
    }

    public function getToolCallCount(): int
    {
        return $this->toolCallCount;
    }

    public function incrementUntrustedResult(): void
    {
        $this->untrustedResultCount++;
    }

    public function getUntrustedResultCount(): int
    {
        return $this->untrustedResultCount;
    }

    public function toArray(): array
    {
        return [
            'tainted' => $this->tainted,
            'taint_source' => $this->taintSource,
            'tainted_at' => $this->taintedAt?->toIso8601String(),
            'tool_call_count' => $this->toolCallCount,
            'untrusted_result_count' => $this->untrustedResultCount,
        ];
    }
}
