<?php

declare(strict_types=1);

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Support\Credentials\CredentialMcpTools;
use App\Support\Credentials\RuntimeCredentialResolver;

/**
 * Exposes the credential vault as a runtime tool so the agent can retrieve
 * logins, API keys, SSH keys, and OAuth tokens during execution.
 *
 * Delegates to CredentialMcpTools which enforces per-credential approval
 * policies based on the credential's approval_mode and the agent's trust score.
 */
class CredentialToolAdapter extends AbstractToolAdapter
{
    public function __construct(
        private readonly CredentialMcpTools $mcpTools,
        private readonly RuntimeCredentialResolver $resolver,
    ) {}

    public function name(): string
    {
        return 'vault';
    }

    public function schema(): array
    {
        return [
            'description' => 'Credential vault — retrieve stored logins, API keys, SSH keys, and OAuth tokens. '
                .'Operations: list_credentials (discover available credentials), '
                .'get_credential (retrieve by provider/name/URL), '
                .'get_login_for_url (get website login for browser automation), '
                .'get_api_key (get API key for a provider). '
                .'Access is governed by each credential\'s approval mode (autonomous/supervised/restricted).',
            'operations' => ['list_credentials', 'get_credential', 'get_login_for_url', 'get_api_key'],
            'parameters' => [
                'operation' => [
                    'type' => 'string',
                    'description' => 'The vault operation to perform.',
                    'required' => true,
                ],
                'provider' => [
                    'type' => 'string',
                    'description' => 'Provider/service name (e.g., "github", "stripe", "x.com").',
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
                    'description' => 'Credential type filter: login, api_key, ssh_key, oauth_token.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search by name, provider, or URL (for list_credentials).',
                ],
            ],
        ];
    }

    protected function getRequiredCapability(array $args): string
    {
        $operation = $args['operation'] ?? '';

        // Listing credentials is a read-only query
        if ($operation === 'list_credentials') {
            return 'vault_read';
        }

        // Retrieving actual secrets requires a higher capability
        return 'vault_retrieve';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);
        $operation = $args['operation'] ?? '';

        // Map adapter operations to CredentialMcpTools tool names
        $toolName = match ($operation) {
            'list_credentials' => 'vault.list_credentials',
            'get_credential' => 'vault.get_credential',
            'get_login_for_url' => 'vault.get_login_for_url',
            'get_api_key' => 'vault.get_api_key',
            default => null,
        };

        if ($toolName === null) {
            return ToolResult::failure(
                "Unknown vault operation: {$operation}. Use: list_credentials, get_credential, get_login_for_url, get_api_key",
                $this->duration($startTime),
            );
        }

        // Extract the trust score from the session's connector (if available)
        $trustScore = $this->resolveTrustScore($context);

        $result = $this->mcpTools->executeTool(
            $toolName,
            $args,
            $context->user,
            $this->resolver,
            $trustScore,
        );

        if ($result['success'] ?? false) {
            return ToolResult::success($result['data'] ?? $result, $this->duration($startTime));
        }

        return ToolResult::failure(
            $result['error'] ?? 'Credential vault request failed.',
            $this->duration($startTime),
        );
    }

    /**
     * Resolve the agent's trust score from the runtime context.
     *
     * The trust score is stored on the runtime session's associated connector
     * account via the delegation trust system. Falls back to 0.0 (untrusted)
     * when no trust score is available.
     */
    private function resolveTrustScore(RuntimeContext $context): float
    {
        $session = $context->session;

        // Try to get trust score from the connector account
        if (method_exists($session, 'connectorAccount') && $session->connectorAccount !== null) {
            $account = $session->connectorAccount;
            if (method_exists($account, 'getTrustScore')) {
                return (float) $account->getTrustScore();
            }
        }

        // Check if trust score is in the session policy
        if (isset($context->policy['trust_score'])) {
            return (float) $context->policy['trust_score'];
        }

        return 0.0;
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
