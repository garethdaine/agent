<?php

namespace App\Services\Credentials;

use App\Models\CredentialVault;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CredentialsManager
{
    public function store(User $user, string $provider, string $key, string $value, ?array $metadata = null): CredentialVault
    {
        $vault = CredentialVault::query()->firstOrNew([
            'user_id' => $user->id,
            'provider' => $provider,
            'key' => $key,
        ]);
        $vault->setDecryptedValue($value);
        $vault->metadata = $metadata;
        $vault->save();

        return $vault;
    }

    public function get(User $user, string $provider, string $key): ?string
    {
        $vault = CredentialVault::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->where('key', $key)
            ->first();

        return $vault?->getDecryptedValue();
    }

    public function delete(User $user, string $provider, string $key): bool
    {
        $deleted = CredentialVault::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->where('key', $key)
            ->delete();

        return $deleted > 0;
    }

    public function redactForAudit(?string $value): string
    {
        return CredentialVault::redactForAudit($value);
    }

    public function getProviderKeys(User $user, string $provider): array
    {
        return CredentialVault::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->pluck('key')
            ->all();
    }
}
