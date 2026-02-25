<?php

namespace Tests\Feature\Messenger;

use App\DTOs\Messenger\AccountLinkPayload;
use App\Models\AccountLinkToken;
use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;
use App\Models\User;
use App\Services\Messenger\AccountLinkTokenService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AccountLinkTest extends TestCase
{
    use RefreshDatabase;

    private ConnectorAccount $connectorAccount;

    private AccountLinkTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectorAccount = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_SLACK,
            'name' => 'Test Slack Workspace',
            'credentials' => ['signing_secret' => 'test_secret'],
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [
                'link_expiration_days' => null,
            ],
            'account_key' => 'T12345678',
        ]);

        $this->tokenService = app(AccountLinkTokenService::class);
    }

    protected function tearDown(): void
    {
        // Always reset Carbon time travel
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_token_generation_creates_valid_token(): void
    {
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }

    public function test_token_validation_returns_payload_for_valid_token(): void
    {
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        $payload = $this->tokenService->validate($token);

        $this->assertNotNull($payload);
        $this->assertInstanceOf(AccountLinkPayload::class, $payload);
        $this->assertEquals('slack', $payload->provider);
        $this->assertEquals('U12345678', $payload->providerUserId);
    }

    public function test_token_validation_returns_null_for_invalid_token(): void
    {
        $payload = $this->tokenService->validate('invalid_token_that_does_not_exist');

        $this->assertNull($payload);
    }

    public function test_token_expiration_after_10_minutes(): void
    {
        // Freeze time at a known point
        $startTime = now();
        Carbon::setTestNow($startTime);

        // Create a token
        $token = $this->tokenService->generate(
            provider: 'telegram',
            providerUserId: '123456789',
            connectorAccountId: $this->connectorAccount->id
        );

        // Token should be valid now
        $this->assertNotNull($this->tokenService->validate($token));

        // Travel forward 11 minutes (past the 10 minute TTL)
        Carbon::setTestNow($startTime->copy()->addMinutes(11));

        // Token should now be invalid
        $payload = $this->tokenService->validate($token);
        $this->assertNull($payload);
    }

    public function test_atomic_consumption_prevents_double_use(): void
    {
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        // First consumption should succeed
        $payload1 = $this->tokenService->consume($token);
        $this->assertNotNull($payload1);
        $this->assertEquals('slack', $payload1->provider);

        // Second consumption should fail
        $payload2 = $this->tokenService->consume($token);
        $this->assertNull($payload2);
    }

    public function test_revoke_invalidates_token(): void
    {
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        // Token should be valid
        $this->assertNotNull($this->tokenService->validate($token));

        // Revoke the token
        $this->tokenService->revoke($token);

        // Token should now be invalid
        $this->assertNull($this->tokenService->validate($token));

        // Consuming should also fail
        $this->assertNull($this->tokenService->consume($token));
    }

    public function test_get_shows_link_page_for_valid_token(): void
    {
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        $response = $this->get(route('messenger.link.show', ['token' => $token]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Messenger/LinkAccount')
                ->has('token')
                ->where('provider', 'slack')
                ->where('error', null)
        );
    }

    public function test_get_shows_error_for_expired_token(): void
    {
        // Freeze time at a known point
        $startTime = now();
        Carbon::setTestNow($startTime);

        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        // Travel forward past expiration
        Carbon::setTestNow($startTime->copy()->addMinutes(11));

        $response = $this->get(route('messenger.link.show', ['token' => $token]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Messenger/LinkAccount')
                ->where('token', null)
                ->has('error')
        );
    }

    public function test_get_shows_error_for_invalid_token(): void
    {
        $response = $this->get(route('messenger.link.show', ['token' => 'invalid_token']));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Messenger/LinkAccount')
                ->where('token', null)
                ->has('error')
        );
    }

    public function test_post_requires_authentication(): void
    {
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        $response = $this->post(route('messenger.link.store', ['token' => $token]));

        $response->assertRedirect(route('login'));
    }

    public function test_post_creates_account_link_record(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        $response = $this->actingAs($user)
            ->post(route('messenger.link.store', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('messenger_identity_links', [
            'user_id' => $user->id,
            'provider_user_id' => 'U12345678',
        ]);
    }

    public function test_post_with_already_consumed_token_fails(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        // Consume the token
        $this->tokenService->consume($token);

        $response = $this->actingAs($user)
            ->post(route('messenger.link.store', ['token' => $token]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Messenger/LinkAccount')
                ->has('error')
        );

        // No link should have been created
        $this->assertDatabaseMissing('messenger_identity_links', [
            'user_id' => $user->id,
            'provider_user_id' => 'U12345678',
        ]);
    }

    public function test_post_with_expired_token_fails(): void
    {
        $user = User::factory()->create();

        // Freeze time at a known point
        $startTime = now();
        Carbon::setTestNow($startTime);

        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        // Travel forward past expiration
        Carbon::setTestNow($startTime->copy()->addMinutes(11));

        $response = $this->actingAs($user)
            ->post(route('messenger.link.store', ['token' => $token]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Messenger/LinkAccount')
                ->has('error')
        );
    }

    public function test_redis_fallback_to_db_for_token_storage(): void
    {
        // Create a second connector for this test
        $telegramConnector = ConnectorAccount::create([
            'provider' => ConnectorAccount::PROVIDER_TELEGRAM,
            'name' => 'Test Telegram Bot',
            'credentials' => ['bot_token' => 'test_token'],
            'connection_mode' => ConnectorAccount::MODE_WEBHOOK,
            'status' => ConnectorAccount::STATUS_CONNECTED,
            'config' => [],
            'account_key' => 'telegram_test',
        ]);

        // Force Redis to fail by using a non-existent connection
        Cache::shouldReceive('store')
            ->with('redis')
            ->andThrow(new \Exception('Redis unavailable'));

        // Re-resolve the service to pick up the mocked cache
        $tokenService = new AccountLinkTokenService;

        $token = $tokenService->generate(
            provider: 'telegram',
            providerUserId: '987654321',
            connectorAccountId: $telegramConnector->id
        );

        // Verify token was stored in DB
        $this->assertDatabaseHas('account_link_tokens', [
            'provider_user_id' => '987654321',
            'connector_account_id' => $telegramConnector->id,
        ]);

        // Token should still be consumable from DB
        $payload = $tokenService->consume($token);
        $this->assertNotNull($payload);
        $this->assertEquals('987654321', $payload->providerUserId);

        // Verify token was marked consumed
        $dbToken = AccountLinkToken::where('provider_user_id', '987654321')->first();
        $this->assertNotNull($dbToken->consumed_at);
    }

    public function test_existing_link_gets_updated_on_relink(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create initial link for user1
        MessengerIdentityLink::create([
            'user_id' => $user1->id,
            'connector_account_id' => $this->connectorAccount->id,
            'provider_user_id' => 'U12345678',
        ]);

        // Generate token and link as user2
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        $response = $this->actingAs($user2)
            ->post(route('messenger.link.store', ['token' => $token]));

        $response->assertRedirect(route('dashboard'));

        // Verify the link was updated to user2
        $link = MessengerIdentityLink::where('provider_user_id', 'U12345678')->first();
        $this->assertEquals($user2->id, $link->user_id);

        // Verify only one link exists
        $this->assertEquals(1, MessengerIdentityLink::where('provider_user_id', 'U12345678')->count());
    }

    public function test_link_expiration_respects_connector_config(): void
    {
        // Update connector to have 30-day expiration
        $this->connectorAccount->update([
            'config' => ['link_expiration_days' => 30],
        ]);

        $user = User::factory()->create();
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U12345678',
            connectorAccountId: $this->connectorAccount->id
        );

        $this->actingAs($user)
            ->post(route('messenger.link.store', ['token' => $token]));

        $link = MessengerIdentityLink::where('provider_user_id', 'U12345678')->first();
        $this->assertNotNull($link->expires_at);
        $this->assertTrue($link->expires_at->isAfter(now()->addDays(29)));
        $this->assertTrue($link->expires_at->isBefore(now()->addDays(31)));
    }

    public function test_link_has_no_expiration_when_config_is_null(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenService->generate(
            provider: 'slack',
            providerUserId: 'U99999999',
            connectorAccountId: $this->connectorAccount->id
        );

        $this->actingAs($user)
            ->post(route('messenger.link.store', ['token' => $token]));

        $link = MessengerIdentityLink::where('provider_user_id', 'U99999999')->first();
        $this->assertNull($link->expires_at);
    }
}
