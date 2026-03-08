<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

final readonly class ProviderResponse
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $error = null,
        public array $rawResponse = [],
    ) {}

    public static function success(string $providerMessageId, array $rawResponse = []): self
    {
        return new self(
            success: true,
            providerMessageId: $providerMessageId,
            rawResponse: $rawResponse,
        );
    }

    public static function failure(string $error, array $rawResponse = []): self
    {
        return new self(
            success: false,
            error: $error,
            rawResponse: $rawResponse,
        );
    }
}
