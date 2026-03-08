<?php

declare(strict_types=1);

namespace App\Support\Connectors\Auth;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorCredentialEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OAuthFlowManager
{
    private const STATE_TTL_SECONDS = 600;

    public function __construct(
        private readonly ConnectorVaultEncrypter $encrypter,
    ) {}

    public function generateAuthorizationUrl(AgentConnector $connector, Team $team, User $user): string
    {
        $authConfig = $connector->auth_config;

        $state = bin2hex(random_bytes(32));
        $codeVerifier = Str::random(86);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        Cache::put("connector_oauth_state:{$state}", [
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'user_id' => $user->id,
            'code_verifier' => $codeVerifier,
        ], self::STATE_TTL_SECONDS);

        $params = [
            'client_id' => $authConfig['client_id'],
            'redirect_uri' => config('app.url') . '/agent/api/v1/connectors/callback',
            'response_type' => 'code',
            'scope' => implode(' ', $authConfig['scopes'] ?? []),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        return $authConfig['authorize_url'] . '?' . http_build_query($params);
    }

    public function handleCallback(string $state, string $code): AgentConnectorConnection
    {
        $stateData = Cache::pull("connector_oauth_state:{$state}");

        if (! $stateData) {
            throw new \RuntimeException('Invalid or expired OAuth state parameter.');
        }

        $connector = AgentConnector::findOrFail($stateData['connector_id']);
        $team = Team::findOrFail($stateData['team_id']);
        $authConfig = $connector->auth_config;

        $response = Http::asForm()->post($authConfig['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('app.url') . '/agent/api/v1/connectors/callback',
            'code_verifier' => $stateData['code_verifier'],
            'client_id' => $authConfig['client_id'],
            'client_secret' => $authConfig['client_secret'],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Token exchange failed: ' . $response->body());
        }

        $tokens = $response->json();

        $encryptedData = $this->encrypter->encrypt($team, json_encode([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'token_type' => $tokens['token_type'] ?? 'Bearer',
        ]));

        $credential = AgentConnectorCredential::create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'oauth2_authorization_code',
            'encrypted_data' => $encryptedData,
            'encryption_key_id' => 'v1',
            'scopes_granted' => explode(' ', $tokens['scope'] ?? ''),
            'token_expires_at' => isset($tokens['expires_in']) ? now()->addSeconds($tokens['expires_in']) : null,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
            'created_by' => $stateData['user_id'],
        ]);

        $connection = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $connector->id)
            ->where('status', AgentConnectorConnection::STATUS_PENDING)
            ->firstOrFail();

        $connection->update([
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'connected_at' => now(),
        ]);

        AgentConnectorCredentialEvent::create([
            'credential_id' => $credential->id,
            'event_type' => 'created',
            'actor_id' => $stateData['user_id'],
            'details' => ['method' => 'oauth2_authorization_code'],
            'created_at' => now(),
        ]);

        return $connection;
    }
}
