<?php

namespace Tests\Unit\Connectors\Jobs;

use App\Jobs\Connectors\RefreshConnectorCredentialsJob;
use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshConnectorCredentialsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_refreshes_expiring_oauth_tokens(): void
    {
        Http::fake([
            'https://auth.example.com/token' => Http::response([
                'access_token' => 'new-token-abc',
                'refresh_token' => 'new-refresh-xyz',
                'expires_in' => 3600,
            ]),
        ]);

        $credential = $this->createOAuthCredential(
            tokenExpiresAt: now()->addMinutes(3),
        );

        $job = new RefreshConnectorCredentialsJob;
        $job->handle(new ConnectorVaultEncrypter);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/token')
                && $request['grant_type'] === 'refresh_token';
        });

        $credential->refresh();
        $this->assertEquals(0, $credential->refresh_failure_count);
        $this->assertNotNull($credential->last_refreshed_at);
    }

    public function test_skips_non_expiring_credentials(): void
    {
        Http::fake();

        $this->createApiKeyCredential();

        $job = new RefreshConnectorCredentialsJob;
        $job->handle(new ConnectorVaultEncrypter);

        Http::assertNothingSent();
    }

    public function test_marks_credential_degraded_after_refresh_failure(): void
    {
        Http::fake([
            'https://auth.example.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $credential = $this->createOAuthCredential(
            tokenExpiresAt: now()->addMinutes(3),
            refreshFailureCount: 2,
        );

        $job = new RefreshConnectorCredentialsJob;
        $job->handle(new ConnectorVaultEncrypter);

        $credential->refresh();
        $this->assertEquals(AgentConnectorCredential::STATUS_DEGRADED, $credential->status);
        $this->assertEquals(3, $credential->refresh_failure_count);
    }

    public function test_logs_credential_event_on_refresh(): void
    {
        Http::fake([
            'https://auth.example.com/token' => Http::response([
                'access_token' => 'new-token',
                'expires_in' => 3600,
            ]),
        ]);

        $credential = $this->createOAuthCredential(
            tokenExpiresAt: now()->addMinutes(3),
        );

        $job = new RefreshConnectorCredentialsJob;
        $job->handle(new ConnectorVaultEncrypter);

        $this->assertDatabaseHas('agent_connector_credential_events', [
            'credential_id' => $credential->id,
            'event_type' => 'refreshed',
        ]);
    }

    public function test_dispatched_on_connector_credentials_queue(): void
    {
        $job = new RefreshConnectorCredentialsJob;

        $this->assertEquals('connector-credentials', $job->queue);
    }

    private function createOAuthCredential(
        ?\DateTimeInterface $tokenExpiresAt = null,
        int $refreshFailureCount = 0,
    ): AgentConnectorCredential {
        $team = Team::factory()->create([
            'connector_vault_key' => str_repeat('b', 64),
        ]);

        $connector = AgentConnector::factory()->create([
            'auth_type' => 'oauth2_authorization_code',
            'auth_config' => [
                'token_url' => 'https://auth.example.com/token',
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
            ],
        ]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        $encrypter = new ConnectorVaultEncrypter;
        $encryptedData = $encrypter->encrypt($team, json_encode([
            'access_token' => 'old-token',
            'refresh_token' => 'old-refresh-token',
        ]));

        return AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'oauth2_authorization_code',
            'encrypted_data' => $encryptedData,
            'token_expires_at' => $tokenExpiresAt ?? now()->addHour(),
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
            'refresh_failure_count' => $refreshFailureCount,
        ]);
    }

    private function createApiKeyCredential(): AgentConnectorCredential
    {
        $team = Team::factory()->create([
            'connector_vault_key' => str_repeat('c', 64),
        ]);

        $connector = AgentConnector::factory()->create([
            'auth_type' => 'api_key',
        ]);

        $connection = AgentConnectorConnection::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
        ]);

        return AgentConnectorCredential::factory()->create([
            'team_id' => $team->id,
            'connector_id' => $connector->id,
            'auth_type' => 'api_key',
            'token_expires_at' => null,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);
    }
}
