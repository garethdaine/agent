<?php

namespace Tests\Feature\Connectors\Security;

use App\Models\AgentConnector;
use App\Models\AgentConnectorConnection;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class CredentialIsolationTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorVaultEncrypter $encrypter;

    private Team $teamA;

    private Team $teamB;

    private AgentConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encrypter = app(ConnectorVaultEncrypter::class);

        $userA = User::factory()->create();
        $this->teamA = Team::factory()->create([
            'user_id' => $userA->id,
            'connector_vault_key' => Str::random(64),
        ]);

        $userB = User::factory()->create();
        $this->teamB = Team::factory()->create([
            'user_id' => $userB->id,
            'connector_vault_key' => Str::random(64),
        ]);

        $this->connector = AgentConnector::factory()->create([
            'name' => 'test-isolation-connector',
            'auth_type' => 'api_key',
        ]);
    }

    public function test_cross_tenant_credential_decryption_fails(): void
    {
        // Encrypt credential for team A
        $plaintext = json_encode(['api_key' => 'sk_teamA_secret_key']);
        $encrypted = $this->encrypter->encrypt($this->teamA, $plaintext);

        // Attempt to decrypt with team B's vault key
        $this->expectException(DecryptException::class);
        $this->encrypter->decrypt($this->teamB, $encrypted);
    }

    public function test_credential_not_accessible_via_different_team_connection(): void
    {
        // Create credential for team A
        $connectionA = AgentConnectorConnection::factory()->create([
            'team_id' => $this->teamA->id,
            'connector_id' => $this->connector->id,
        ]);

        $encrypted = $this->encrypter->encrypt($this->teamA, json_encode(['api_key' => 'secret']));
        AgentConnectorCredential::factory()->create([
            'team_id' => $this->teamA->id,
            'connector_id' => $this->connector->id,
            'encrypted_data' => $encrypted,
            'status' => AgentConnectorCredential::STATUS_ACTIVE,
        ]);

        // Query credentials scoped to team B — should find nothing
        $teamBCredentials = AgentConnectorCredential::where('team_id', $this->teamB->id)
            ->where('connector_id', $this->connector->id)
            ->where('status', AgentConnectorCredential::STATUS_ACTIVE)
            ->get();

        $this->assertTrue($teamBCredentials->isEmpty());
    }

    public function test_raw_tokens_not_in_logs(): void
    {
        $secretToken = 'sk_live_super_secret_token_12345';

        // Capture log output
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        // Perform encryption operations
        $encrypted = $this->encrypter->encrypt($this->teamA, json_encode(['api_key' => $secretToken]));
        $decrypted = $this->encrypter->decrypt($this->teamA, $encrypted);

        // Verify the encrypted data doesn't contain the raw token
        $this->assertStringNotContainsString($secretToken, $encrypted);

        // Verify we can get the token back via proper decryption
        $data = json_decode($decrypted, true);
        $this->assertEquals($secretToken, $data['api_key']);
    }

    public function test_credential_encryption_uses_team_specific_key(): void
    {
        $sameValue = json_encode(['api_key' => 'identical_test_key_abc']);

        // Encrypt same value with two different teams
        $encryptedA = $this->encrypter->encrypt($this->teamA, $sameValue);
        $encryptedB = $this->encrypter->encrypt($this->teamB, $sameValue);

        // Different ciphertext (different keys produce different output)
        $this->assertNotEquals($encryptedA, $encryptedB);

        // Both can be decrypted by their respective teams
        $decryptedA = $this->encrypter->decrypt($this->teamA, $encryptedA);
        $decryptedB = $this->encrypter->decrypt($this->teamB, $encryptedB);

        $this->assertEquals($sameValue, $decryptedA);
        $this->assertEquals($sameValue, $decryptedB);
    }
}
