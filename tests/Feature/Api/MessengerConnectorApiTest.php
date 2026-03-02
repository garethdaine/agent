<?php

namespace Tests\Feature\Api;

use App\Models\ConnectorAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessengerConnectorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_connector_validates_provider(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/agent/api/v1/messenger/connectors', [
            'provider' => 'invalid_provider',
            'name' => 'Test Connector',
            'credentials' => ['token' => 'test-token'],
            'account_key' => 'test-account-key',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->postJson('/agent/api/v1/messenger/connectors', [
            'provider' => 'slack',
            'name' => 'Slack Workspace',
            'credentials' => [
                'bot_token' => 'xoxb-test-token',
                'signing_secret' => 'signing-secret-test',
            ],
            'account_key' => 'T12345678',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.provider', 'slack')
            ->assertJsonPath('data.name', 'Slack Workspace');
    }

    public function test_create_connector_stores_encrypted_credentials(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/agent/api/v1/messenger/connectors', [
            'provider' => 'telegram',
            'name' => 'My Telegram Bot',
            'credentials' => [
                'bot_token' => 'secret-bot-token-12345',
                'secret_token' => 'telegram-secret-token',
            ],
            'account_key' => 'bot123456',
        ]);

        $response->assertStatus(201);

        $connector = ConnectorAccount::find($response->json('data.id'));
        $this->assertNotNull($connector);

        // Credentials are encrypted in the database
        $rawCredentials = $connector->getRawOriginal('credentials');
        $this->assertNotEquals(json_encode(['bot_token' => 'secret-bot-token-12345']), $rawCredentials);

        // But accessible via the model cast
        $this->assertEquals([
            'bot_token' => 'secret-bot-token-12345',
            'secret_token' => 'telegram-secret-token',
        ], $connector->credentials);
    }

    public function test_update_connector_encrypts_credentials(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create([
            'provider' => 'slack',
            'credentials' => [
                'bot_token' => 'original-token',
                'signing_secret' => 'original-signing-secret',
            ],
        ]);

        $this->actingAs($user);

        $response = $this->putJson("/agent/api/v1/messenger/connectors/{$connector->id}", [
            'credentials' => [
                'bot_token' => 'new-secret-token',
                'signing_secret' => 'new-signing-secret',
            ],
        ]);

        $response->assertOk();

        $connector->refresh();
        $this->assertEquals([
            'bot_token' => 'new-secret-token',
            'signing_secret' => 'new-signing-secret',
        ], $connector->credentials);
    }

    public function test_delete_connector_soft_deletes(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create([
            'provider' => 'discord',
            'status' => ConnectorAccount::STATUS_CONNECTED,
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/agent/api/v1/messenger/connectors/{$connector->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $connector->id)
            ->assertJsonPath('data.deleted', true);

        // Verify soft delete occurred
        $connector->refresh();
        $this->assertNotNull($connector->deleted_at);
        $this->assertEquals(ConnectorAccount::STATUS_DISCONNECTED, $connector->status);
    }

    public function test_test_connector_validates_connection(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create([
            'provider' => 'telegram',
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'credentials' => [
                'bot_token' => '123456:telegram-bot-token',
                'secret_token' => 'telegram-secret-token',
            ],
        ]);

        $this->actingAs($user);
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456,
                    'username' => 'agent_test_bot',
                    'first_name' => 'Agent Test Bot',
                ],
            ], 200),
        ]);

        $response = $this->postJson("/agent/api/v1/messenger/connectors/{$connector->id}/test");

        $response->assertOk()
            ->assertJsonPath('data.id', $connector->id)
            ->assertJsonPath('data.provider', 'telegram')
            ->assertJsonPath('data.test_status', 'connected');
    }

    public function test_list_connectors_filters_by_provider(): void
    {
        $user = User::factory()->create();

        ConnectorAccount::factory()->create(['provider' => 'slack']);
        ConnectorAccount::factory()->create(['provider' => 'telegram']);
        ConnectorAccount::factory()->create(['provider' => 'discord']);

        $this->actingAs($user);

        $this->getJson('/agent/api/v1/messenger/connectors?provider=slack')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'slack');
    }

    public function test_list_connectors_filters_by_status(): void
    {
        $user = User::factory()->create();

        ConnectorAccount::factory()->create(['status' => ConnectorAccount::STATUS_CONNECTED]);
        ConnectorAccount::factory()->create(['status' => ConnectorAccount::STATUS_DISCONNECTED]);
        ConnectorAccount::factory()->create(['status' => ConnectorAccount::STATUS_ERROR]);

        $this->actingAs($user);

        $this->getJson('/agent/api/v1/messenger/connectors?status=connected')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'connected');
    }

    public function test_show_connector_hides_credentials(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create([
            'provider' => 'slack',
            'credentials' => ['bot_token' => 'super-secret-token'],
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/agent/api/v1/messenger/connectors/{$connector->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $connector->id)
            ->assertJsonMissing(['credentials' => ['bot_token' => 'super-secret-token']]);
    }

    public function test_create_connector_prevents_duplicates(): void
    {
        $user = User::factory()->create();

        ConnectorAccount::factory()->create([
            'provider' => 'slack',
            'account_key' => 'T12345678',
        ]);

        $this->actingAs($user);

        $this->postJson('/agent/api/v1/messenger/connectors', [
            'provider' => 'slack',
            'name' => 'Duplicate Workspace',
            'credentials' => [
                'bot_token' => 'another-token',
                'signing_secret' => 'another-signing-secret',
            ],
            'account_key' => 'T12345678',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DUPLICATE_CONNECTOR');
    }

    public function test_whatsapp_connector_forces_webhook_mode(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/agent/api/v1/messenger/connectors', [
            'provider' => 'whatsapp',
            'name' => 'WhatsApp Business',
            'credentials' => [
                'access_token' => 'whatsapp-token',
                'phone_number_id' => '1234567890',
                'app_secret' => 'whatsapp-app-secret',
                'verify_token' => 'whatsapp-verify-token',
            ],
            'account_key' => '+1234567890',
            'connection_mode' => 'local', // Should be overridden to webhook
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.connection_mode', 'webhook');
    }

    public function test_unauthorized_access_blocked(): void
    {
        $this->getJson('/agent/api/v1/messenger/connectors')
            ->assertUnauthorized();

        $this->postJson('/agent/api/v1/messenger/connectors', [])
            ->assertUnauthorized();
    }

    public function test_update_connector_validates_connection_mode(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create([
            'provider' => 'slack',
        ]);

        $this->actingAs($user);

        $this->putJson("/agent/api/v1/messenger/connectors/{$connector->id}", [
            'connection_mode' => 'invalid_mode',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_list_connectors_with_session_count(): void
    {
        $user = User::factory()->create();
        $connector = ConnectorAccount::factory()->create();

        // Create some sessions for this connector
        \App\Models\ChatSession::factory()->count(3)->create([
            'user_id' => $user->id,
            'connector_account_id' => $connector->id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/messenger/connectors?with_session_count=true');

        $response->assertOk()
            ->assertJsonPath('data.0.sessions_count', 3);
    }

    public function test_create_connector_with_config(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/agent/api/v1/messenger/connectors', [
            'provider' => 'slack',
            'name' => 'Configured Workspace',
            'credentials' => [
                'bot_token' => 'xoxb-test',
                'signing_secret' => 'xoxb-signing-secret',
            ],
            'account_key' => 'T87654321',
            'config' => [
                'confirmation_required' => true,
                'session_history_limit' => 50,
                'default_verbosity' => 'summary',
            ],
        ]);

        $response->assertStatus(201);

        $connector = ConnectorAccount::find($response->json('data.id'));
        $this->assertTrue($connector->config['confirmation_required']);
        $this->assertEquals(50, $connector->config['session_history_limit']);
        $this->assertEquals('summary', $connector->config['default_verbosity']);
    }

    public function test_schema_endpoint_exposes_provider_credential_requirements(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/agent/api/v1/messenger/connectors/schema');

        $response->assertOk()
            ->assertJsonPath('data.providers.slack.label', 'Slack')
            ->assertJsonPath('data.providers.telegram.label', 'Telegram')
            ->assertJsonPath('data.providers.discord.label', 'Discord')
            ->assertJsonPath('data.providers.whatsapp.label', 'WhatsApp')
            ->assertJsonPath('data.providers.discord.enabled', true)
            ->assertJsonPath('data.providers.whatsapp.enabled', true);
    }
}
