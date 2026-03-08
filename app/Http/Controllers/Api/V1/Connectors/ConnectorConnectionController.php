<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Connectors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Connectors\ConnectRequest;
use App\Http\Resources\Connectors\ConnectionHealthResource;
use App\Http\Resources\Connectors\ConnectionResource;
use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Connectors\Auth\ApiKeyAuthManager;
use App\Support\Connectors\Auth\OAuthFlowManager;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ConnectorConnectionController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $flags,
        private readonly OAuthFlowManager $oauthManager,
        private readonly ApiKeyAuthManager $apiKeyManager,
        private readonly ConnectorVaultEncrypter $encrypter,
    ) {}

    public function connect(ConnectRequest $request, string $id): JsonResponse
    {
        $this->ensureConnectorsEnabled();

        $connector = AgentConnector::findOrFail($id);
        /** @var \App\Models\User $user */
        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        // Ensure team has a vault key
        if (! $team->connector_vault_key) {
            $team->update(['connector_vault_key' => Str::random(64)]);
            $team->refresh();
        }

        if ($this->isOAuthConnector($connector)) {
            $authUrl = $this->oauthManager->generateAuthorizationUrl($connector, $team, $user);

            // Create pending connection
            AgentConnectorConnection::create([
                'team_id' => $team->id,
                'connector_id' => $connector->id,
                'status' => AgentConnectorConnection::STATUS_PENDING,
                'connected_by' => $user->id,
            ]);

            return response()->json([
                'data' => ['authorization_url' => $authUrl],
            ]);
        }

        // API key flow
        $credential = $this->apiKeyManager->storeApiKey(
            connector: $connector,
            team: $team,
            user: $user,
            apiKey: $request->validated('api_key', ''),
            apiSecret: $request->validated('api_secret'),
        );

        $connection = AgentConnectorConnection::create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
            'connected_by' => $user->id,
            'connected_at' => now(),
        ]);

        return (new ConnectionResource($connection->load('connector')))
            ->response()
            ->setStatusCode(201);
    }

    public function disconnect(Request $request, string $id): JsonResponse
    {
        $this->ensureConnectorsEnabled();

        /** @var \App\Models\User $user */
        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        $connection = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $id)
            ->whereNot('status', AgentConnectorConnection::STATUS_DISCONNECTED)
            ->firstOrFail();

        Gate::authorize('manage', $connection);

        $connection->update([
            'status' => AgentConnectorConnection::STATUS_DISCONNECTED,
            'disconnected_by' => $user->id,
            'disconnected_at' => now(),
        ]);

        return response()->json(null, 204);
    }

    public function test(Request $request, string $id): JsonResponse
    {
        $this->ensureConnectorsEnabled();

        /** @var \App\Models\User $user */
        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        $connection = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $id)
            ->firstOrFail();

        $connector = $connection->connector;
        $startTime = microtime(true);
        $connected = false;

        try {
            $credential = $connection->credential;
            if ($credential && $team->connector_vault_key) {
                $decrypted = json_decode($this->encrypter->decrypt($team, $credential->encrypted_data), true);
                $baseUrl = $connector->base_url ?? 'https://api.example.com';

                $response = Http::withToken($decrypted['access_token'] ?? $decrypted['api_key'] ?? '')
                    ->timeout(10)
                    ->get($baseUrl);

                $connected = $response->successful();
            }
        } catch (\Throwable) {
            $connected = false;
        }

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        return response()->json([
            'data' => [
                'connected' => $connected,
                'latency_ms' => $latencyMs,
            ],
        ]);
    }

    public function health(Request $request, string $id): JsonResponse
    {
        $this->ensureConnectorsEnabled();

        /** @var \App\Models\User $user */
        $user = $request->user();
        $team = $user->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        $connection = AgentConnectorConnection::where('team_id', $team->id)
            ->where('connector_id', $id)
            ->firstOrFail();

        return (new ConnectionHealthResource($connection))->response();
    }

    private function isOAuthConnector(AgentConnector $connector): bool
    {
        return str_starts_with($connector->auth_type ?? '', 'oauth2_');
    }

    private function ensureConnectorsEnabled(): void
    {
        if (! $this->flags->enabled(FeatureFlagManager::CONNECTORS_ENABLED)) {
            abort(404, 'Connectors feature is not enabled.');
        }
    }
}
