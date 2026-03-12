<?php

declare(strict_types=1);

namespace App\Support\Credentials;

use App\Models\CredentialVault;
use App\Models\User;

class CredentialMcpTools
{
    /**
     * Get MCP tool definitions for credential vault access.
     *
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'name' => 'vault.list_credentials',
                'description' => 'List available credentials in the user\'s vault. Returns names, types, and providers only — no secrets. Use this to discover what credentials are available before requesting specific ones.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => CredentialVault::VALID_TYPES,
                            'description' => 'Filter by credential type.',
                        ],
                        'search' => [
                            'type' => 'string',
                            'description' => 'Search by name, provider, or URL.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'vault.get_credential',
                'description' => 'Retrieve a specific credential by provider, name, or URL. Returns decrypted secrets if the approval policy allows it. The credential\'s approval_mode and your trust score determine whether access is granted, requires user confirmation, or is denied.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => [
                            'type' => 'string',
                            'description' => 'Provider/service name (e.g., "github", "aws", "x.com").',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Credential name to search for.',
                        ],
                        'url' => [
                            'type' => 'string',
                            'description' => 'URL to match against stored credentials.',
                        ],
                        'type' => [
                            'type' => 'string',
                            'enum' => CredentialVault::VALID_TYPES,
                            'description' => 'Credential type filter.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'vault.get_login_for_url',
                'description' => 'Get website login credentials (username/password) for a specific URL. Used for browser automation — matches the URL domain against stored login credentials. Requires appropriate approval level.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The website URL to find login credentials for.',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
            [
                'name' => 'vault.get_api_key',
                'description' => 'Get API key credentials for a named provider/service. Returns the API key, secret, and any associated metadata (base URL, headers). Requires appropriate approval level.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'provider' => [
                            'type' => 'string',
                            'description' => 'The provider/service name (e.g., "openai", "stripe", "aws").',
                        ],
                    ],
                    'required' => ['provider'],
                ],
            ],
        ];
    }

    /**
     * Execute an MCP tool call.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function executeTool(
        string $toolName,
        array $arguments,
        User $user,
        RuntimeCredentialResolver $resolver,
        float $trustScore = 0.0,
    ): array {
        return match ($toolName) {
            'vault.list_credentials' => $this->handleListCredentials($user, $arguments),
            'vault.get_credential' => $this->handleGetCredential($user, $resolver, $arguments, $trustScore),
            'vault.get_login_for_url' => $this->handleGetLoginForUrl($user, $resolver, $arguments, $trustScore),
            'vault.get_api_key' => $this->handleGetApiKey($user, $resolver, $arguments, $trustScore),
            default => ['success' => false, 'error' => "Unknown tool: {$toolName}"],
        };
    }

    private function handleListCredentials(User $user, array $arguments): array
    {
        $query = CredentialVault::query()
            ->where('user_id', $user->id)
            ->forRuntime();

        if (isset($arguments['type'])) {
            $query->ofType($arguments['type']);
        }

        if (isset($arguments['search'])) {
            $query->searchable($arguments['search']);
        }

        $credentials = $query->get()->map(fn (CredentialVault $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'provider' => $c->provider,
            'type' => $c->credential_type,
            'url' => $c->url,
            'approval_mode' => $c->approval_mode,
            'last_used_at' => $c->last_used_at?->toIso8601String(),
            'expires_at' => $c->expires_at?->toIso8601String(),
        ]);

        return ['success' => true, 'data' => ['credentials' => $credentials->toArray()]];
    }

    private function handleGetCredential(User $user, RuntimeCredentialResolver $resolver, array $arguments, float $trustScore): array
    {
        $query = new CredentialQuery(
            provider: $arguments['provider'] ?? null,
            url: $arguments['url'] ?? null,
            type: $arguments['type'] ?? null,
            name: $arguments['name'] ?? null,
        );

        $result = $resolver->resolve($user, $query, $trustScore);

        return $this->formatResult($result);
    }

    private function handleGetLoginForUrl(User $user, RuntimeCredentialResolver $resolver, array $arguments, float $trustScore): array
    {
        $url = $arguments['url'] ?? null;
        if ($url === null) {
            return ['success' => false, 'error' => 'URL is required.'];
        }

        $result = $resolver->resolveForBrowser($user, $url, $trustScore);

        return $this->formatResult($result);
    }

    private function handleGetApiKey(User $user, RuntimeCredentialResolver $resolver, array $arguments, float $trustScore): array
    {
        $provider = $arguments['provider'] ?? null;
        if ($provider === null) {
            return ['success' => false, 'error' => 'Provider is required.'];
        }

        $result = $resolver->resolveApiKey($user, $provider, $trustScore);

        return $this->formatResult($result);
    }

    private function formatResult(RuntimeCredentialResult $result): array
    {
        if ($result->isAllowed()) {
            return [
                'success' => true,
                'data' => [
                    'credentials' => $result->credentials,
                    'credential_id' => $result->credentialId,
                    'credential_type' => $result->credentialType,
                ],
            ];
        }

        return [
            'success' => false,
            'decision' => $result->decision->value,
            'credential_id' => $result->credentialId,
            'error' => $result->reason,
        ];
    }
}
