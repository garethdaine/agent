<?php

namespace Tests\Unit\Connectors;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\Auth\OAuthFlowManager;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthFlowManagerTest extends TestCase
{
    use RefreshDatabase;

    private OAuthFlowManager $manager;

    private ConnectorVaultEncrypter $encrypter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encrypter = new ConnectorVaultEncrypter;
        $this->manager = new OAuthFlowManager($this->encrypter);
    }

    public function test_generates_authorization_url_with_pkce(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'authorize_url' => 'https://login.example.com/authorize',
                'token_url' => 'https://login.example.com/token',
                'scopes' => ['read', 'write'],
                'pkce' => true,
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('a', 64)]);
        $user = User::factory()->create();

        $url = $this->manager->generateAuthorizationUrl($connector, $team, $user);

        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        $this->assertEquals('https', $parsed['scheme']);
        $this->assertEquals('login.example.com', $parsed['host']);
        $this->assertEquals('/authorize', $parsed['path']);
        $this->assertEquals('test-client-id', $params['client_id']);
        $this->assertEquals('code', $params['response_type']);
        $this->assertArrayHasKey('code_challenge', $params);
        $this->assertEquals('S256', $params['code_challenge_method']);
        $this->assertArrayHasKey('state', $params);
        $this->assertStringContainsString('read', $params['scope']);
        $this->assertStringContainsString(config('app.url') . '/agent/api/v1/connectors/callback', $params['redirect_uri']);
    }

    public function test_stores_state_in_cache_with_ttl(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'authorize_url' => 'https://login.example.com/authorize',
                'token_url' => 'https://login.example.com/token',
                'scopes' => ['read'],
                'pkce' => true,
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('b', 64)]);
        $user = User::factory()->create();

        $url = $this->manager->generateAuthorizationUrl($connector, $team, $user);

        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);
        $state = $params['state'];

        $cached = Cache::get("connector_oauth_state:{$state}");
        $this->assertNotNull($cached);
        $this->assertEquals($team->id, $cached['team_id']);
        $this->assertEquals($connector->id, $cached['connector_id']);
        $this->assertEquals($user->id, $cached['user_id']);
        $this->assertArrayHasKey('code_verifier', $cached);
    }

    public function test_exchanges_code_for_tokens(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'authorize_url' => 'https://login.example.com/authorize',
                'token_url' => 'https://login.example.com/token',
                'scopes' => ['read'],
                'pkce' => true,
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('c', 64)]);
        $user = User::factory()->create();

        $url = $this->manager->generateAuthorizationUrl($connector, $team, $user);
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);
        $state = $params['state'];

        Http::fake([
            'login.example.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'read',
            ]),
        ]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_PENDING,
            'connected_by' => $user->id,
        ]);

        $result = $this->manager->handleCallback($state, 'auth-code-123');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://login.example.com/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'auth-code-123'
                && isset($request['code_verifier']);
        });
    }

    public function test_stores_encrypted_credentials_after_exchange(): void
    {
        $connector = AgentConnector::factory()->create([
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'authorize_url' => 'https://login.example.com/authorize',
                'token_url' => 'https://login.example.com/token',
                'scopes' => ['read'],
                'pkce' => true,
            ],
        ]);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('d', 64)]);
        $user = User::factory()->create();

        $url = $this->manager->generateAuthorizationUrl($connector, $team, $user);
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        Http::fake([
            'login.example.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'read',
            ]),
        ]);

        AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'status' => AgentConnectorConnection::STATUS_PENDING,
            'connected_by' => $user->id,
        ]);

        $this->manager->handleCallback($params['state'], 'auth-code-456');

        $credential = AgentConnectorCredential::where('team_id', $team->id)
            ->where('connector_id', $connector->id)
            ->first();

        $this->assertNotNull($credential);
        $encryptedData = $credential->encrypted_data;
        if (is_resource($encryptedData)) {
            $encryptedData = stream_get_contents($encryptedData);
        }
        $this->assertNotEquals('new-access-token', $encryptedData);

        $decrypted = json_decode($this->encrypter->decrypt($team, $encryptedData), true);
        $this->assertEquals('new-access-token', $decrypted['access_token']);
        $this->assertEquals('new-refresh-token', $decrypted['refresh_token']);
    }

    public function test_rejects_invalid_state_parameter(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired OAuth state');

        $this->manager->handleCallback('nonexistent-state', 'code-123');
    }

    public function test_rejects_expired_state(): void
    {
        Cache::put('connector_oauth_state:expired-state', [
            'team_id' => 'some-id',
            'connector_id' => 'some-connector',
            'user_id' => 'some-user',
            'code_verifier' => 'some-verifier',
        ], 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired OAuth state');

        $this->manager->handleCallback('expired-state', 'code-123');
    }
}
