<?php

declare(strict_types=1);

namespace App\Support\Connectors\Pipeline;

use App\Models\AgentConnectorCredential;
use App\Support\Connectors\ActionRequest;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Closure;
use RuntimeException;

class CredentialManager
{
    public function __construct(
        private readonly ConnectorVaultEncrypter $encrypter,
    ) {}

    public function handle(ActionRequest $request, Closure $next): mixed
    {
        $credential = AgentConnectorCredential::where('team_id', $request->team->id)
            ->where('connector_id', $request->connector->id)
            ->where('status', AgentConnectorCredential::STATUS_ACTIVE)
            ->first();

        if (! $credential) {
            throw new RuntimeException("No credential found for connection {$request->connection->id}.");
        }

        $encryptedData = $credential->encrypted_data;
        if (is_resource($encryptedData)) {
            $encryptedData = stream_get_contents($encryptedData);
        }

        $decrypted = json_decode(
            $this->encrypter->decrypt($request->team, (string) $encryptedData),
            true,
        );

        $authHeaders = $this->buildAuthHeaders($credential->auth_type, $decrypted);

        $request->connection->setAttribute('_auth_headers', $authHeaders);
        $credential->update(['last_used_at' => now()]);

        return $next($request);
    }

    public function buildAuthHeaders(string $authType, array $credentials): array
    {
        return match ($authType) {
            'oauth2_authorization_code', 'oauth2_client_credentials' => [
                'Authorization' => 'Bearer ' . ($credentials['access_token'] ?? ''),
            ],
            'api_key' => [
                ($credentials['header_name'] ?? 'X-API-Key') => $credentials['api_key'] ?? '',
            ],
            'api_key_secret' => [
                ($credentials['header_name'] ?? 'X-API-Key') => $credentials['api_key'] ?? '',
                ($credentials['secret_header_name'] ?? 'X-API-Secret') => $credentials['api_secret'] ?? '',
            ],
            'basic' => [
                'Authorization' => 'Basic ' . base64_encode(
                    ($credentials['username'] ?? '') . ':' . ($credentials['password'] ?? '')
                ),
            ],
            'custom_header' => [
                ($credentials['header_name'] ?? 'Authorization') => $credentials['header_value'] ?? '',
            ],
            default => throw new RuntimeException("Unsupported auth type: {$authType}"),
        };
    }
}
