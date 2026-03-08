<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\DTOs\Connectors\ConnectionTestResult;
use App\DTOs\Connectors\ConnectResult;
use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorCredentialEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\Auth\ApiKeyAuthManager;
use App\Support\Connectors\Auth\OAuthFlowManager;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ConnectionLifecycleService
{
    public function __construct(
        private readonly OAuthFlowManager $oauthManager,
        private readonly ApiKeyAuthManager $apiKeyManager,
        private readonly ConnectorVaultEncrypter $encrypter,
    ) {}

    public function connect(AgentConnector $connector, Team $team, User $user, ?array $authParams = null): ConnectResult
    {
        $this->ensureVaultKey($team);
        $this->ensureConnectionLimit($team);
        $this->ensureNoActiveConnection($connector, $team);

        if ($connector->auth_type === 'oauth2_authorization_code') {
            $connection = AgentConnectorConnection::create([
                'team_id' => $team->id,
                'connector_id' => $connector->id,
                'status' => AgentConnectorConnection::STATUS_PENDING,
                'health_score' => 0,
                'connected_by' => $user->id,
            ]);

            $url = $this->oauthManager->generateAuthorizationUrl($connector, $team, $user);

            return ConnectResult::pendingOAuth($connection, $url);
        }

        $connection = AgentConnectorConnection::create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'health_score' => 1.00,
            'connected_by' => $user->id,
            'connected_at' => now(),
        ]);

        $this->apiKeyManager->storeApiKey(
            $connector,
            $team,
            $user,
            $authParams['api_key'],
            $authParams['api_secret'] ?? null,
        );

        return ConnectResult::connected($connection);
    }

    public function disconnect(AgentConnectorConnection $connection, User $user): void
    {
        $credential = AgentConnectorCredential::where('team_id', $connection->team_id)
            ->where('connector_id', $connection->connector_id)
            ->where('status', AgentConnectorCredential::STATUS_ACTIVE)
            ->first();

        if ($credential) {
            $credential->update([
                'status' => AgentConnectorCredential::STATUS_REVOKED,
                'revoked_by' => $user->id,
                'revoked_at' => now(),
            ]);

            AgentConnectorCredentialEvent::create([
                'credential_id' => $credential->id,
                'event_type' => 'revoked',
                'actor_id' => $user->id,
                'details' => ['reason' => 'manual_disconnect'],
                'created_at' => now(),
            ]);
        }

        $connection->update([
            'status' => AgentConnectorConnection::STATUS_DISCONNECTED,
            'disconnected_by' => $user->id,
            'disconnected_at' => now(),
        ]);
    }

    public function testConnection(AgentConnectorConnection $connection): ConnectionTestResult
    {
        $connector = $connection->connector;
        $team = $connection->team;
        $credential = AgentConnectorCredential::where('team_id', $connection->team_id)
            ->where('connector_id', $connection->connector_id)
            ->where('status', AgentConnectorCredential::STATUS_ACTIVE)
            ->first();

        if (! $credential) {
            return ConnectionTestResult::failed('No credentials found for this connection.');
        }

        $encryptedData = $credential->encrypted_data;
        if (is_resource($encryptedData)) {
            $encryptedData = stream_get_contents($encryptedData);
        }

        $decrypted = json_decode($this->encrypter->decrypt($team, $encryptedData), true);

        $action = $this->findTestAction($connector);
        $url = rtrim($connector->base_url, '/').'/'.ltrim($action['path'], '/');

        $headers = $this->buildAuthHeaders($connector, $decrypted);

        $start = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get($url);

            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            if ($response->successful()) {
                return ConnectionTestResult::healthy($latencyMs);
            }

            return ConnectionTestResult::failed(
                "HTTP {$response->status()}: {$response->body()}",
                $latencyMs
            );
        } catch (ConnectionException $e) {
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return ConnectionTestResult::failed($e->getMessage(), $latencyMs);
        }
    }

    private function ensureVaultKey(Team $team): void
    {
        if (empty($team->connector_vault_key)) {
            $team->connector_vault_key = Str::random(64);
            $team->save();
        }
    }

    private function ensureConnectionLimit(Team $team): void
    {
        $maxConnections = config('connectors.max_connections_per_team', 20);
        $activeCount = AgentConnectorConnection::where('team_id', $team->id)
            ->whereIn('status', [
                AgentConnectorConnection::STATUS_CONNECTED,
                AgentConnectorConnection::STATUS_PENDING,
                AgentConnectorConnection::STATUS_DEGRADED,
            ])
            ->count();

        if ($activeCount >= $maxConnections) {
            throw new \RuntimeException(
                "Team has reached the maximum number of connections ({$maxConnections})."
            );
        }
    }

    private function ensureNoActiveConnection(AgentConnector $connector, Team $team): void
    {
        $existing = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $connector->id)
            ->whereIn('status', [
                AgentConnectorConnection::STATUS_CONNECTED,
                AgentConnectorConnection::STATUS_PENDING,
            ])
            ->exists();

        if ($existing) {
            throw new \RuntimeException(
                "Team already has an active connection to connector '{$connector->name}'."
            );
        }
    }

    private function findTestAction(AgentConnector $connector): array
    {
        $actions = $connector->actions ?? [];

        foreach ($actions as $action) {
            if (strtoupper($action['method'] ?? '') === 'GET') {
                return $action;
            }
        }

        return ['path' => '/', 'method' => 'GET'];
    }

    private function buildAuthHeaders(AgentConnector $connector, array $credentials): array
    {
        $authConfig = $connector->auth_config;
        $header = $authConfig['header'] ?? 'Authorization';

        if (isset($credentials['access_token'])) {
            $tokenType = $credentials['token_type'] ?? 'Bearer';

            return [$header => "{$tokenType} {$credentials['access_token']}"];
        }

        return [$header => $credentials['api_key']];
    }
}
