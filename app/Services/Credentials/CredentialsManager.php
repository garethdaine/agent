<?php

declare(strict_types=1);

namespace App\Services\Credentials;

use App\Models\CredentialUsageLog;
use App\Models\CredentialVault;
use App\Models\User;
use App\Support\Credentials\ApprovalDecision;
use Illuminate\Support\Collection;

class CredentialsManager
{
    // ─── Legacy provider-based API (backward compatible) ───

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

    // ─── Vault CRUD (new password-manager API) ───

    /**
     * Store a new credential in the vault.
     *
     * @param  array{name?: string, provider: string, key?: string, url?: string, secrets: array, metadata?: array, approval_mode?: string, expires_at?: string|null, notes?: string|null}  $data
     */
    public function storeCredential(User $user, string $type, array $data): CredentialVault
    {
        $vault = new CredentialVault;
        $vault->user_id = $user->id;
        $vault->credential_type = $type;
        $vault->provider = $data['provider'];
        $vault->key = $data['key'] ?? 'default';
        $vault->name = $data['name'] ?? null;
        $vault->url = $data['url'] ?? null;
        $vault->approval_mode = $data['approval_mode'] ?? CredentialVault::APPROVAL_SUPERVISED;
        $vault->metadata = $data['metadata'] ?? null;

        $vault->setDecryptedPayload($data['secrets']);

        if (isset($data['notes'])) {
            $vault->setEncryptedNotes($data['notes']);
        }

        if (isset($data['expires_at'])) {
            $vault->expires_at = $data['expires_at'];
        }

        $vault->save();

        $this->logUsage($vault, $user, CredentialUsageLog::ACTION_CREATED, 'allowed');

        return $vault;
    }

    /**
     * Update an existing credential.
     *
     * @param  array{name?: string, provider?: string, key?: string, url?: string, secrets?: array, metadata?: array, approval_mode?: string, expires_at?: string|null, notes?: string|null}  $data
     */
    public function updateCredential(CredentialVault $credential, array $data): CredentialVault
    {
        if (isset($data['name'])) {
            $credential->name = $data['name'];
        }
        if (isset($data['provider'])) {
            $credential->provider = $data['provider'];
        }
        if (isset($data['key'])) {
            $credential->key = $data['key'];
        }
        if (isset($data['url'])) {
            $credential->url = $data['url'];
        }
        if (isset($data['approval_mode'])) {
            $credential->approval_mode = $data['approval_mode'];
        }
        if (isset($data['metadata'])) {
            $credential->metadata = $data['metadata'];
        }
        if (array_key_exists('expires_at', $data)) {
            $credential->expires_at = $data['expires_at'];
        }
        if (isset($data['secrets'])) {
            $credential->setDecryptedPayload($data['secrets']);
        }
        if (array_key_exists('notes', $data)) {
            $credential->setEncryptedNotes($data['notes']);
        }

        $credential->save();

        $this->logUsage($credential, $credential->user, CredentialUsageLog::ACTION_UPDATED, 'allowed');

        return $credential;
    }

    /**
     * Rotate the secrets for a credential.
     */
    public function rotateCredential(CredentialVault $credential, array $newSecrets): CredentialVault
    {
        $credential->setDecryptedPayload($newSecrets);
        $credential->last_rotated_at = now();
        $credential->save();

        $this->logUsage($credential, $credential->user, CredentialUsageLog::ACTION_ROTATED, 'allowed');

        return $credential;
    }

    /**
     * List credentials for a user, optionally filtered by type and search term.
     *
     * @return Collection<int, CredentialVault>
     */
    public function listCredentials(User $user, ?string $type = null, ?string $search = null): Collection
    {
        $query = CredentialVault::query()->where('user_id', $user->id);

        if ($type !== null) {
            $query->ofType($type);
        }

        if ($search !== null) {
            $query->searchable($search);
        }

        return $query->orderBy('name')->orderBy('provider')->get();
    }

    /**
     * Get a credential for AI runtime use by provider name.
     */
    public function getForRuntime(User $user, string $provider, ?string $type = null): ?CredentialVault
    {
        $query = CredentialVault::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->forRuntime();

        if ($type !== null) {
            $query->ofType($type);
        }

        return $query->first();
    }

    /**
     * Find a login credential matching a URL by domain.
     */
    public function resolveForUrl(User $user, string $url): ?CredentialVault
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return null;
        }

        // Strip 'www.' prefix for matching
        $domain = preg_replace('/^www\./', '', $host);

        return CredentialVault::query()
            ->where('user_id', $user->id)
            ->ofType(CredentialVault::TYPE_LOGIN)
            ->forRuntime()
            ->where(function ($q) use ($domain, $host) {
                $q->whereRaw('LOWER(url) LIKE ?', ['%'.mb_strtolower($domain).'%'])
                    ->orWhereRaw('LOWER(provider) LIKE ?', ['%'.mb_strtolower($domain).'%']);

                if ($host !== $domain) {
                    $q->orWhereRaw('LOWER(url) LIKE ?', ['%'.mb_strtolower($host).'%']);
                }
            })
            ->first();
    }

    /**
     * Delete a vault credential by ID.
     */
    public function deleteCredential(CredentialVault $credential): bool
    {
        $this->logUsage($credential, $credential->user, CredentialUsageLog::ACTION_DELETED, 'allowed');

        return (bool) $credential->delete();
    }

    /**
     * Check if a credential can be used given a trust score.
     */
    public function checkApproval(CredentialVault $credential, float $trustScore): ApprovalDecision
    {
        if ($credential->isExpired()) {
            return ApprovalDecision::Denied;
        }

        if ($credential->requiresApproval($trustScore)) {
            return ApprovalDecision::NeedsConfirmation;
        }

        return ApprovalDecision::Allowed;
    }

    private function logUsage(CredentialVault $credential, ?User $user, string $action, string $outcome, array $context = []): void
    {
        CredentialUsageLog::create([
            'credential_id' => $credential->id,
            'user_id' => $user?->id ?? $credential->user_id,
            'action' => $action,
            'context' => $context,
            'outcome' => $outcome,
        ]);
    }
}
