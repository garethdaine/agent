<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Models\ConnectorAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConnectorTestEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function discord_test_validates_credentials_and_calls_api(): void
    {
        Http::fake([
            'discord.com/api/*' => Http::response(['username' => 'TestBot', 'id' => '123456789'], 200),
        ]);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->discord()->webhookMode()->create([
            'credentials' => [
                'bot_token' => 'valid-token',
                'application_id' => '123456789012345678',
                'public_key' => str_repeat('a', 64),
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/messenger/connectors/{$account->id}/test");

        $response->assertOk();
        $response->assertJsonPath('data.test_status', 'connected');
        $response->assertJsonStructure([
            'data' => [
                'provider',
                'test_status',
                'message',
                'details' => ['bot_username'],
            ],
        ]);
    }

    #[Test]
    public function discord_test_returns_failure_for_invalid_token(): void
    {
        Http::fake([
            'discord.com/api/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->discord()->webhookMode()->create([
            'credentials' => [
                'bot_token' => 'invalid-token',
                'application_id' => '123456789012345678',
                'public_key' => str_repeat('a', 64),
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/messenger/connectors/{$account->id}/test");

        $response->assertOk();
        $response->assertJsonPath('data.test_status', 'failed');
    }

    #[Test]
    public function discord_test_registers_slash_commands_on_success(): void
    {
        Http::fake([
            'discord.com/api/v10/users/@me' => Http::response(['username' => 'TestBot', 'id' => '123456789'], 200),
            'discord.com/api/v10/applications/*/commands' => Http::response([
                ['id' => '1', 'name' => 'jobs'],
                ['id' => '2', 'name' => 'runs'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->discord()->webhookMode()->create([
            'credentials' => [
                'bot_token' => 'valid-token',
                'application_id' => '123456789012345678',
                'public_key' => str_repeat('a', 64),
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/messenger/connectors/{$account->id}/test");

        $response->assertOk();
        $response->assertJsonPath('data.test_status', 'connected');
        $response->assertJsonPath('data.details.slash_commands.registered', true);
    }

    #[Test]
    public function whatsapp_test_validates_credentials_and_calls_api(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '123456789',
                'display_phone_number' => '+1234567890',
                'verified_name' => 'Test Business',
            ], 200),
        ]);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->whatsapp()->webhookMode()->create([
            'credentials' => [
                'access_token' => 'valid-token',
                'phone_number_id' => '123456789',
                'app_secret' => 'app-secret-value',
                'verify_token' => 'webhook-verify',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/messenger/connectors/{$account->id}/test");

        $response->assertOk();
        $response->assertJsonPath('data.test_status', 'connected');
        $response->assertJsonStructure([
            'data' => [
                'provider',
                'test_status',
                'message',
                'details',
                'setup',
            ],
        ]);
    }

    #[Test]
    public function whatsapp_test_returns_failure_for_invalid_token(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token.',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 401),
        ]);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->whatsapp()->webhookMode()->create([
            'credentials' => [
                'access_token' => 'invalid-token',
                'phone_number_id' => '123456789',
                'app_secret' => 'app-secret-value',
                'verify_token' => 'webhook-verify',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/messenger/connectors/{$account->id}/test");

        $response->assertOk();
        $response->assertJsonPath('data.test_status', 'failed');
    }

    #[Test]
    public function whatsapp_test_returns_setup_guidance_with_webhook_url(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '123456789',
                'display_phone_number' => '+1234567890',
                'verified_name' => 'Test Business',
            ], 200),
        ]);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->whatsapp()->webhookMode()->create([
            'credentials' => [
                'access_token' => 'valid-token',
                'phone_number_id' => '123456789',
                'app_secret' => 'app-secret-value',
                'verify_token' => 'webhook-verify',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/messenger/connectors/{$account->id}/test");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'setup' => ['webhook_url'],
            ],
        ]);
    }

    #[Test]
    public function discord_test_returns_derived_account_key(): void
    {
        Http::fake([
            'discord.com/api/v10/users/@me' => Http::response([
                'username' => 'TestBot',
                'id' => '123456789',
            ], 200),
            'discord.com/api/v10/applications/*/commands' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->discord()->webhookMode()->create([
            'credentials' => [
                'bot_token' => 'valid-token',
                'application_id' => '123456789012345678',
                'public_key' => str_repeat('a', 64),
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/messenger/connectors/{$account->id}/test");

        $response->assertOk();
        $response->assertJsonPath('data.test_status', 'connected');

        // Verify account_key was derived from application_id
        $account->refresh();
        $this->assertEquals('123456789012345678', $account->account_key);
    }

    #[Test]
    public function whatsapp_test_returns_derived_account_key(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '123456789',
                'display_phone_number' => '+1234567890',
                'verified_name' => 'Test Business',
            ], 200),
        ]);

        $user = User::factory()->create();
        $account = ConnectorAccount::factory()->whatsapp()->webhookMode()->create([
            'credentials' => [
                'access_token' => 'valid-token',
                'phone_number_id' => '987654321',
                'app_secret' => 'app-secret-value',
                'verify_token' => 'webhook-verify',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson("/agent/api/v1/messenger/connectors/{$account->id}/test");

        $response->assertOk();
        $response->assertJsonPath('data.test_status', 'connected');

        // Verify account_key was derived from phone_number_id
        $account->refresh();
        $this->assertEquals('987654321', $account->account_key);
    }
}
