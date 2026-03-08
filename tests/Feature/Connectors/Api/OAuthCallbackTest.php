<?php

declare(strict_types=1);

namespace Tests\Feature\Connectors\Api;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_exchanges_code_for_tokens(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test_client',
                'client_secret' => 'test_secret',
                'authorize_url' => 'https://provider.example.com/authorize',
                'token_url' => 'https://provider.example.com/token',
                'scopes' => ['read'],
                'pkce' => true,
            ],
        ]);

        $team = Team::factory()->create(['connector_vault_key' => \Illuminate\Support\Str::random(64)]);
        $user = User::factory()->create();

        AgentConnectorConnection::factory()->pending()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'connected_by' => $user->id,
        ]);

        $state = bin2hex(random_bytes(16));
        Cache::put("connector_oauth_state:{$state}", [
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'user_id' => $user->id,
            'code_verifier' => 'test_verifier',
        ], 600);

        Http::fake([
            'provider.example.com/token' => Http::response([
                'access_token' => 'mock_access_token',
                'refresh_token' => 'mock_refresh_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'read',
            ], 200),
        ]);

        $response = $this->get("/agent/api/v1/connectors/callback?state={$state}&code=test_auth_code");

        $response->assertRedirect();

        $this->assertDatabaseHas('agent_connector_connections', [
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_CONNECTED,
        ]);
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $response = $this->get('/agent/api/v1/connectors/callback?state=invalid_state&code=test_code');

        $response->assertStatus(400);
    }

    public function test_callback_route_does_not_require_auth(): void
    {
        // Without any authentication, the route should still be accessible
        // (it will fail on state validation, not auth)
        $response = $this->get('/agent/api/v1/connectors/callback?state=no_auth_test&code=test');

        // Should get 400 (bad state), not 401 (unauthorized)
        $this->assertNotEquals(401, $response->status());
    }
}
