<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Validation;

use App\Messenger\Validation\DiscordCredentialValidator;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for DiscordCredentialValidator.
 *
 * Assumptions:
 * - Discord Bot token starts with MTE/MTA/etc (base64-encoded snowflake)
 * - Application ID is a snowflake (numeric string up to 20 digits)
 * - Public key is a 64-character hex string (Ed25519)
 *
 * Mode Requirements:
 * - Local mode (Gateway): bot_token, application_id
 * - Webhook mode: bot_token, application_id, public_key
 *
 * Validation is performed in two phases:
 * 1. Format validation (presence checks, basic format)
 * 2. API validation (/users/@me call with bot token)
 */
class DiscordCredentialValidatorTest extends TestCase
{
    use RefreshDatabase;

    private DiscordCredentialValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DiscordCredentialValidator();
    }

    public function test_local_mode_requires_bot_token_and_application_id(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
            'application_id' => '1123456789012345678',
        ];

        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'TestBot',
                'discriminator' => '0000',
                'bot' => true,
                'verified' => true,
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
        $this->assertEquals('TestBot', $result->getMetadata()['bot_username']);
    }

    public function test_local_mode_fails_without_bot_token(): void
    {
        $credentials = [
            'application_id' => '1123456789012345678',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['bot_token']));
    }

    public function test_local_mode_fails_without_application_id(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('application_id', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['application_id']));
    }

    public function test_webhook_mode_requires_bot_token_application_id_and_public_key(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
            'application_id' => '1123456789012345678',
            'public_key' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
        ];

        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'TestBot',
                'discriminator' => '0000',
                'bot' => true,
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_webhook_mode_fails_without_public_key(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
            'application_id' => '1123456789012345678',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('public_key', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['public_key']));
    }

    public function test_webhook_mode_fails_without_bot_token(): void
    {
        $credentials = [
            'application_id' => '1123456789012345678',
            'public_key' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
    }

    public function test_token_validation_via_users_me_api_call(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
            'application_id' => '1123456789012345678',
        ];

        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'MyAgentBot',
                'discriminator' => '0000',
                'bot' => true,
                'verified' => true,
                'avatar' => 'abc123',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/v10/users/@me'
                && str_starts_with($request->header('Authorization')[0], 'Bot ');
        });
    }

    public function test_returns_bot_username_and_app_info_on_success(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
            'application_id' => '1123456789012345678',
        ];

        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'id' => '1123456789012345678',
                'username' => 'AgentAssistantBot',
                'discriminator' => '0000',
                'bot' => true,
                'verified' => true,
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());
        $this->assertEquals('AgentAssistantBot', $result->getMetadata()['bot_username']);
        $this->assertEquals('1123456789012345678', $result->getMetadata()['bot_id']);
    }

    public function test_api_failure_returns_validation_error(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.invalidtoken',
            'application_id' => '1123456789012345678',
        ];

        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response([
                'code' => 0,
                'message' => '401: Unauthorized',
            ], 401),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('Unauthorized', $result->getErrors()['bot_token']);
    }

    public function test_invalid_public_key_format_returns_validation_error(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
            'application_id' => '1123456789012345678',
            'public_key' => 'too-short',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('public_key', $result->getErrors());
        $this->assertStringContainsString('64', $result->getErrors()['public_key']);
    }

    public function test_http_error_during_validation_returns_error(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
            'application_id' => '1123456789012345678',
        ];

        Http::fake([
            'https://discord.com/api/v10/users/@me' => Http::response(null, 500),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_application_id_must_be_numeric(): void
    {
        $credentials = [
            'bot_token' => 'FAKE_DISCORD_BOT_TOKEN.TEST_USE_ONLY.abcdefghijklmnopqrstuvwxyz1234567890',
            'application_id' => 'not-a-number',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('application_id', $result->getErrors());
        $this->assertStringContainsString('numeric', strtolower($result->getErrors()['application_id']));
    }
}
