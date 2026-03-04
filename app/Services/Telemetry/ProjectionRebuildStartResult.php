<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

final class ProjectionRebuildStartResult
{
    private function __construct(
        private readonly bool $conflict,
        private readonly ?string $buildId,
        private readonly ?string $activeBuildId,
        private readonly ?string $rebuildingBuildId,
        private readonly string $message,
    ) {}

    public static function started(string $buildId, ?string $activeBuildId): self
    {
        return new self(
            conflict: false,
            buildId: $buildId,
            activeBuildId: $activeBuildId,
            rebuildingBuildId: $buildId,
            message: 'Projection rebuild started.',
        );
    }

    public static function conflict(?string $activeBuildId, ?string $rebuildingBuildId): self
    {
        return new self(
            conflict: true,
            buildId: null,
            activeBuildId: $activeBuildId,
            rebuildingBuildId: $rebuildingBuildId,
            message: 'Projection rebuild is already in progress.',
        );
    }

    public function isConflict(): bool
    {
        return $this->conflict;
    }

    public function buildId(): ?string
    {
        return $this->buildId;
    }

    public function activeBuildId(): ?string
    {
        return $this->activeBuildId;
    }

    public function rebuildingBuildId(): ?string
    {
        return $this->rebuildingBuildId;
    }

    public function message(): string
    {
        return $this->message;
    }
}
