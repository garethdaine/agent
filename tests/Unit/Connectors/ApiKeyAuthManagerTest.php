<?php

declare(strict_types=1);

namespace Tests\Unit\Connectors;

use App\Models\AgentConnector;
use App\Models\AgentConnectorCredential;
use App\Models\Team;
use App\Models\User;
use App\Support\Connectors\Auth\ApiKeyAuthManager;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyAuthManagerTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyAuthManager $manager;

    private ConnectorVaultEncrypter $encrypter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encrypter = new ConnectorVaultEncrypter;
        $this->manager = new ApiKeyAuthManager($this->encrypter);
    }

    public function test_stores_api_key_encrypted(): void
    {
        $connector = AgentConnector::factory()->create(['auth_type' => 'api_key']);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('e', 64)]);
        $user = User::factory()->create();

        $credential = $this->manager->storeApiKey($connector, $team, $user, 'sk-test-key-12345');

        $this->assertInstanceOf(AgentConnectorCredential::class, $credential);
        $this->assertEquals('api_key', $credential->auth_type);
        $this->assertNotEquals('sk-test-key-12345', $credential->encrypted_data);
        $this->assertNotEmpty($credential->encrypted_data);
    }

    public function test_stores_api_key_and_secret_pair(): void
    {
        $connector = AgentConnector::factory()->create(['auth_type' => 'api_key_secret']);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('f', 64)]);
        $user = User::factory()->create();

        $credential = $this->manager->storeApiKey(
            $connector, $team, $user,
            'api-key-abc', 'api-secret-xyz'
        );

        $decrypted = json_decode($this->encrypter->decrypt($team, $credential->encrypted_data), true);
        $this->assertEquals('api-key-abc', $decrypted['api_key']);
        $this->assertEquals('api-secret-xyz', $decrypted['api_secret']);
    }

    public function test_retrieves_decrypted_api_key(): void
    {
        $connector = AgentConnector::factory()->create(['auth_type' => 'api_key']);
        $team = Team::factory()->create(['connector_vault_key' => str_repeat('g', 64)]);
        $user = User::factory()->create();

        $credential = $this->manager->storeApiKey($connector, $team, $user, 'my-secret-key');

        $result = $this->manager->retrieveApiKey($credential, $team);

        $this->assertEquals('my-secret-key', $result['api_key']);
        $this->assertNull($result['api_secret']);
    }
}
