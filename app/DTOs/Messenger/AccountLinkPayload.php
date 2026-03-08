<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

readonly class AccountLinkPayload
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public int $createdAt,
        public ?string $connectorAccountId = null
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_user_id' => $this->providerUserId,
            'created_at' => $this->createdAt,
            'connector_account_id' => $this->connectorAccountId,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            provider: $data['provider'],
            providerUserId: $data['provider_user_id'],
            createdAt: $data['created_at'],
            connectorAccountId: $data['connector_account_id'] ?? null
        );
    }
}
