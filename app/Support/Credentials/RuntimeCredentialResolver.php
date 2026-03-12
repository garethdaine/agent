<?php

declare(strict_types=1);

namespace App\Support\Credentials;

use App\Models\CredentialUsageLog;
use App\Models\CredentialVault;
use App\Models\User;
use App\Services\Credentials\CredentialsManager;

class RuntimeCredentialResolver
{
    public function __construct(
        private readonly CredentialsManager $manager,
    ) {}

    /**
     * Resolve a credential matching the given query, checking approval policy.
     */
    public function resolve(User $user, CredentialQuery $query, float $trustScore): RuntimeCredentialResult
    {
        $credential = $this->findCredential($user, $query);

        if ($credential === null) {
            return new RuntimeCredentialResult(
                decision: ApprovalDecision::Denied,
                reason: 'No matching credential found.',
            );
        }

        return $this->resolveWithApproval($credential, $user, $trustScore, [
            'query' => array_filter([
                'provider' => $query->provider,
                'url' => $query->url,
                'type' => $query->type,
                'name' => $query->name,
            ]),
        ]);
    }

    /**
     * Resolve login credentials for a URL (browser automation).
     */
    public function resolveForBrowser(User $user, string $url, float $trustScore): RuntimeCredentialResult
    {
        $credential = $this->manager->resolveForUrl($user, $url);

        if ($credential === null) {
            return new RuntimeCredentialResult(
                decision: ApprovalDecision::Denied,
                reason: "No login credentials found for URL: {$url}",
            );
        }

        return $this->resolveWithApproval($credential, $user, $trustScore, [
            'url' => $url,
            'tool' => 'vault.get_login_for_url',
        ]);
    }

    /**
     * Resolve an API key by provider name.
     */
    public function resolveApiKey(User $user, string $provider, float $trustScore): RuntimeCredentialResult
    {
        $credential = $this->manager->getForRuntime($user, $provider, CredentialVault::TYPE_API_KEY);

        if ($credential === null) {
            return new RuntimeCredentialResult(
                decision: ApprovalDecision::Denied,
                reason: "No API key found for provider: {$provider}",
            );
        }

        return $this->resolveWithApproval($credential, $user, $trustScore, [
            'provider' => $provider,
            'tool' => 'vault.get_api_key',
        ]);
    }

    private function findCredential(User $user, CredentialQuery $query): ?CredentialVault
    {
        // Try URL-based lookup first (for login type)
        if ($query->url !== null) {
            $byUrl = $this->manager->resolveForUrl($user, $query->url);
            if ($byUrl !== null) {
                return $byUrl;
            }
        }

        // Try provider-based lookup
        if ($query->provider !== null) {
            return $this->manager->getForRuntime($user, $query->provider, $query->type);
        }

        // Try name-based search
        if ($query->name !== null) {
            $query_builder = CredentialVault::query()
                ->where('user_id', $user->id)
                ->forRuntime()
                ->searchable($query->name);

            if ($query->type !== null) {
                $query_builder->ofType($query->type);
            }

            return $query_builder->first();
        }

        return null;
    }

    private function resolveWithApproval(
        CredentialVault $credential,
        User $user,
        float $trustScore,
        array $context,
    ): RuntimeCredentialResult {
        $decision = $this->manager->checkApproval($credential, $trustScore);

        $this->logUsage($credential, $user, $decision, $context);

        if ($decision === ApprovalDecision::Allowed) {
            $credential->markUsed();

            return new RuntimeCredentialResult(
                decision: ApprovalDecision::Allowed,
                credentials: $credential->getDecryptedPayload(),
                credentialId: $credential->id,
                credentialType: $credential->credential_type,
            );
        }

        $reason = match ($decision) {
            ApprovalDecision::NeedsConfirmation => "Credential '{$credential->name}' requires user confirmation (mode: {$credential->approval_mode}, trust: {$trustScore}).",
            ApprovalDecision::Denied => "Credential '{$credential->name}' has expired.",
            default => 'Access denied.',
        };

        return new RuntimeCredentialResult(
            decision: $decision,
            credentialId: $credential->id,
            credentialType: $credential->credential_type,
            reason: $reason,
        );
    }

    private function logUsage(CredentialVault $credential, User $user, ApprovalDecision $decision, array $context): void
    {
        CredentialUsageLog::create([
            'credential_id' => $credential->id,
            'user_id' => $user->id,
            'action' => CredentialUsageLog::ACTION_RETRIEVED,
            'context' => $context,
            'outcome' => $decision->value,
        ]);
    }
}
