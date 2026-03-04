<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

final class ProjectionBuildActivationResult
{
    private function __construct(
        private readonly string $state,
        private readonly string $buildId,
        private readonly ?string $currentStatus,
        private readonly ?string $activeBuildId,
        private readonly ?string $previousActiveBuildId,
        private readonly ?string $rebuildingBuildId,
        private readonly ?string $activatedAtIso8601,
        private readonly string $message,
    ) {}

    public static function notFound(
        string $buildId,
        ?string $activeBuildId,
        ?string $rebuildingBuildId,
    ): self {
        return new self(
            state: 'not_found',
            buildId: $buildId,
            currentStatus: null,
            activeBuildId: $activeBuildId,
            previousActiveBuildId: null,
            rebuildingBuildId: $rebuildingBuildId,
            activatedAtIso8601: null,
            message: 'Projection build was not found.',
        );
    }

    public static function parityRequired(
        string $buildId,
        string $currentStatus,
        ?string $activeBuildId,
        ?string $rebuildingBuildId,
    ): self {
        return new self(
            state: 'parity_required',
            buildId: $buildId,
            currentStatus: $currentStatus,
            activeBuildId: $activeBuildId,
            previousActiveBuildId: null,
            rebuildingBuildId: $rebuildingBuildId,
            activatedAtIso8601: null,
            message: 'Replay build activation requires a parity-passed build.',
        );
    }

    public static function activated(
        string $buildId,
        ?string $previousActiveBuildId,
        string $activatedAtIso8601,
    ): self {
        return new self(
            state: 'activated',
            buildId: $buildId,
            currentStatus: 'active',
            activeBuildId: $buildId,
            previousActiveBuildId: $previousActiveBuildId,
            rebuildingBuildId: null,
            activatedAtIso8601: $activatedAtIso8601,
            message: 'Projection replay build activated.',
        );
    }

    public function isNotFound(): bool
    {
        return $this->state === 'not_found';
    }

    public function isParityRequired(): bool
    {
        return $this->state === 'parity_required';
    }

    public function isActivated(): bool
    {
        return $this->state === 'activated';
    }

    public function buildId(): string
    {
        return $this->buildId;
    }

    public function currentStatus(): ?string
    {
        return $this->currentStatus;
    }

    public function activeBuildId(): ?string
    {
        return $this->activeBuildId;
    }

    public function previousActiveBuildId(): ?string
    {
        return $this->previousActiveBuildId;
    }

    public function rebuildingBuildId(): ?string
    {
        return $this->rebuildingBuildId;
    }

    public function activatedAtIso8601(): ?string
    {
        return $this->activatedAtIso8601;
    }

    public function message(): string
    {
        return $this->message;
    }
}
