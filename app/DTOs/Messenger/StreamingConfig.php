<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

class StreamingConfig
{
    public function __construct(
        public readonly int $maxMessageChars = 2000,
        public readonly float $throttleMs = 1200,
        public readonly int $minInitialChars = 200,
        public readonly string $cursor = ' ▍',
        public readonly bool $supportsNativeStreaming = false,
    ) {}

    public function throttleSeconds(): float
    {
        return $this->throttleMs / 1000;
    }

    public static function discord(): self
    {
        return new self(
            maxMessageChars: 2000,
            throttleMs: 1200,
        );
    }

    public static function telegram(): self
    {
        return new self(
            maxMessageChars: 4096,
            throttleMs: 1000,
        );
    }

    public static function slack(): self
    {
        return new self(
            maxMessageChars: 4000,
            throttleMs: 1000,
            supportsNativeStreaming: true,
        );
    }

    public static function fallback(): self
    {
        return new self;
    }
}
