<?php

namespace App\Support\Delegation\DTOs;

readonly class TrustScore
{
    public function __construct(
        public float $score,
        public string $confidence,
        public array $components,
        public int $sampleSize,
        public ?string $source,
    ) {}

    public function isLowTrust(): bool
    {
        return $this->score < 0.4;
    }

    public function isMediumTrust(): bool
    {
        return $this->score >= 0.4 && $this->score < 0.8;
    }

    public function isHighTrust(): bool
    {
        return $this->score >= 0.8;
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'confidence' => $this->confidence,
            'components' => $this->components,
            'sample_size' => $this->sampleSize,
            'source' => $this->source,
        ];
    }
}
