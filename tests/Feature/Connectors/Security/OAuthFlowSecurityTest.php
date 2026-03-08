<?php

namespace Tests\Feature\Connectors\Security;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\Team;
use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Connectors\Auth\OAuthFlowManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OAuthFlowSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private AgentConnector $oauthConnector;

    private OAuthFlowManager $oauthManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'user_id' => $this->user->id,
            'connector_vault_key' => Str::random(64),
        ]);
        $this->user->switchTeam($this->team);

        $this->oauthConnector = AgentConnector::factory()->create([
            'name' => 'test-oauth-security',
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'client_id' => 'test_client_id',
                'client_secret' => 'test_client_secret',
                'authorize_url' => 'https://provider.example.com/authorize',
                'token_url' => 'https://provider.example.com/token',
                'scopes' => ['read', 'write'],
                'pkce' => true,
            ],
        ]);

        $this->oauthManager = app(OAuthFlowManager::class);

        FeatureFlagManager::enable(FeatureFlagManager::CONNECTORS_ENABLED);
    }

    public function test_oauth_state_is_single_use(): void
    {
        // Create pending connection for the callback to find
        AgentConnectorConnection::factory()->create([
            'team_id' => $this->team->id,
            'connector_id' => $this->oauthConnector->id,
            'status' => AgentConnectorConnection::STATUS_PENDING,
        ]);

        // Generate authorization URL (stores state in cache)
        $url = $this->oauthManager->generateAuthorizationUrl(
            $this->oauthConnector,
            $this->team,
            $this->user
        );

        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $state = $params['state'];

        // Mock token exchange
        Http::fake([
            'provider.example.com/token' => Http::response([
                'access_token' => 'token_xyz',
                'refresh_token' => 'refresh_abc',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'read write',
            ], 200),
        ]);

        // First use: should succeed
        $this->oauthManager->handleCallback($state, 'auth_code_123');

        // Second use: state has been consumed (Cache::pull)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired OAuth state parameter.');
        $this->oauthManager->handleCallback($state, 'auth_code_456');
    }

    public function test_oauth_state_expires_after_ttl(): void
    {
        $url = $this->oauthManager->generateAuthorizationUrl(
            $this->oauthConnector,
            $this->team,
            $this->user
        );

        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $state = $params['state'];

        // Verify state exists
        $this->assertNotNull(Cache::get("connector_oauth_state:{$state}"));

        // Simulate TTL expiry by manually forgetting the cache entry
        Cache::forget("connector_oauth_state:{$state}");

        // Attempt to use expired state
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired OAuth state parameter.');
        $this->oauthManager->handleCallback($state, 'auth_code_123');
    }

    public function test_pkce_code_verifier_is_cryptographically_random(): void
    {
        $verifiers = [];

        for ($i = 0; $i < 100; $i++) {
            $url = $this->oauthManager->generateAuthorizationUrl(
                $this->oauthConnector,
                $this->team,
                $this->user
            );

            parse_str(parse_url($url, PHP_URL_QUERY), $params);
            $state = $params['state'];

            // Pull the state data from cache to inspect the code_verifier
            $stateData = Cache::get("connector_oauth_state:{$state}");
            $this->assertNotNull($stateData);
            $verifier = $stateData['code_verifier'];

            // Verify minimum length (RFC 7636 requires 43-128 chars)
            $this->assertGreaterThanOrEqual(43, strlen($verifier));

            $verifiers[] = $verifier;

            // Clean up the cache entry
            Cache::forget("connector_oauth_state:{$state}");
        }

        // All 100 verifiers should be unique
        $uniqueVerifiers = array_unique($verifiers);
        $this->assertCount(100, $uniqueVerifiers, 'All PKCE code verifiers should be unique');
    }

    public function test_callback_validates_state_before_code_exchange(): void
    {
        Http::fake();

        // Attempt callback with non-existent state
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired OAuth state parameter.');

        $this->oauthManager->handleCallback('non_existent_state', 'auth_code_123');

        // No HTTP call should have been made for token exchange
        Http::assertNothingSent();
    }
}
