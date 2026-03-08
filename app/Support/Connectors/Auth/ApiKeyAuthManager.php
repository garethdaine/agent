<?php

declare(strict_types=1);

namespace App\Support\Connectors\Auth;

use App\Models\AgentConnector;
use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorCredentialEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\ConnectorVaultEncrypter;

class ApiKeyAuthManager
{
    public function __construct(
        private readonly ConnectorVaultEncrypter $encrypter,
    ) {}

    public function storeApiKey(
        AgentConnector $connector,
        Team $team,
        User $user,
        string $apiKey,
        ?string $apiSecret = null,
    ): AgentConnectorCredential {
        $encryptedData = $this->encrypter->encrypt($team, json_encode([
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ]));

        $credential = AgentConnectorCredential::create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => $connector->auth_type,
            'encrypted_data' => $encryptedData,
            'encryption_key_id' => 'v1',
            'scopes_granted' => [],
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
            'created_by' => $user->id,
        ]);

        AgentConnectorCredentialEvent::create([
            'credential_id' => $credential->id,
            'event_type' => 'created',
            'actor_id' => $user->id,
            'details' => ['method' => $connector->auth_type],
            'created_at' => now(),
        ]);

        return $credential;
    }

    public function retrieveApiKey(AgentConnectorCredential $credential, Team $team): array
    {
        $decrypted = json_decode($this->encrypter->decrypt($team, $credential->encrypted_data), true);

        return [
            'api_key' => $decrypted['api_key'],
            'api_secret' => $decrypted['api_secret'] ?? null,
        ];
    }
}
