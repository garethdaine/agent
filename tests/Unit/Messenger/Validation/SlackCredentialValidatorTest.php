<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Validation;

use App\Messenger\Validation\SlackCredentialValidator;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for SlackCredentialValidator.
 *
 * Assumptions:
 * - Slack Socket Mode requires app_token (xapp-*) for WebSocket connection
 * - All modes require bot_token (xoxb-*) for API calls
 * - Webhook mode requires signing_secret for signature verification
 * - Token format validation happens before API calls
 * - auth.test API validates token and returns bot info
 *
 * Token Formats:
 * - App tokens: xapp-1-{app_id}-{timestamp}-{hash}
 * - Bot tokens: xoxb-{team_id}-{bot_user_id}-{secret}
 * - User tokens: xoxp-{team_id}-{user_id}-{secret} (not used here)
 */
class SlackCredentialValidatorTest extends TestCase
{
    use RefreshDatabase;

    private SlackCredentialValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SlackCredentialValidator;
    }

    public function test_local_mode_requires_app_token_xapp_and_bot_token_xoxb(): void
    {
        $credentials = [
            'app_token' => 'xapp-1-AFAKE123456-9999999999999-FAKEAPPSECRETFORTESTING',
            'bot_token' => 'xoxb-111-222-FAKESECRET',
        ];

        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'url' => 'https://myteam.slack.com/',
                'team' => 'My Team',
                'user' => 'mybot',
                'team_id' => 'T0123456789',
                'user_id' => 'U0123456789',
                'bot_id' => 'B0123456789',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
        $this->assertEquals('mybot', $result->getMetadata()['bot_username']);
    }

    public function test_local_mode_fails_without_app_token(): void
    {
        $credentials = [
            'bot_token' => 'xoxb-111-222-FAKESECRET',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('app_token', $result->getErrors());
        $this->assertStringContainsString('required', $result->getErrors()['app_token']);
    }

    public function test_local_mode_fails_without_bot_token(): void
    {
        $credentials = [
            'app_token' => 'xapp-1-AFAKE123456-9999999999999-FAKEAPPSECRETFORTESTING',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('required', $result->getErrors()['bot_token']);
    }

    public function test_webhook_mode_requires_bot_token_xoxb_and_signing_secret(): void
    {
        $credentials = [
            'bot_token' => 'xoxb-111-222-FAKESECRET',
            'signing_secret' => 'abcdef0123456789abcdef0123456789',
        ];

        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'url' => 'https://myteam.slack.com/',
                'team' => 'My Team',
                'user' => 'mybot',
                'team_id' => 'T0123456789',
                'user_id' => 'U0123456789',
                'bot_id' => 'B0123456789',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_webhook_mode_fails_without_signing_secret(): void
    {
        $credentials = [
            'bot_token' => 'xoxb-111-222-FAKESECRET',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('signing_secret', $result->getErrors());
        $this->assertStringContainsString('required', $result->getErrors()['signing_secret']);
    }

    public function test_webhook_mode_fails_without_bot_token(): void
    {
        $credentials = [
            'signing_secret' => 'abcdef0123456789abcdef0123456789',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
    }

    public function test_invalid_app_token_format_returns_validation_error(): void
    {
        $credentials = [
            'app_token' => 'invalid-token-format',
            'bot_token' => 'xoxb-111-222-FAKESECRET',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('app_token', $result->getErrors());
        $this->assertStringContainsString('format', strtolower($result->getErrors()['app_token']));
    }

    public function test_invalid_bot_token_format_returns_validation_error(): void
    {
        $credentials = [
            'app_token' => 'xapp-1-AFAKE123456-9999999999999-FAKEAPPSECRETFORTESTING',
            'bot_token' => 'invalid-bot-token',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('format', strtolower($result->getErrors()['bot_token']));
    }

    public function test_auth_test_api_validates_token_and_returns_bot_info(): void
    {
        $credentials = [
            'bot_token' => 'xoxb-111-222-FAKESECRET',
            'signing_secret' => 'abcdef0123456789abcdef0123456789',
        ];

        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'url' => 'https://myteam.slack.com/',
                'team' => 'My Team',
                'user' => 'mybot',
                'team_id' => 'T0123456789',
                'user_id' => 'U0123456789',
                'bot_id' => 'B0123456789',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertTrue($result->isValid());
        $this->assertEquals('mybot', $result->getMetadata()['bot_username']);
        $this->assertEquals('T0123456789', $result->getMetadata()['team_id']);
        $this->assertEquals('B0123456789', $result->getMetadata()['bot_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/auth.test'
                && str_contains($request->header('Authorization')[0], 'Bearer ');
        });
    }

    public function test_auth_test_api_failure_returns_validation_error(): void
    {
        $credentials = [
            'bot_token' => 'xoxb-111-222-FAKESECRET',
            'signing_secret' => 'abcdef0123456789abcdef0123456789',
        ];

        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => false,
                'error' => 'invalid_auth',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('invalid_auth', $result->getErrors()['bot_token']);
    }

    public function test_returns_bot_username_on_success(): void
    {
        $credentials = [
            'app_token' => 'xapp-1-AFAKE123456-9999999999999-FAKEAPPSECRETFORTESTING',
            'bot_token' => 'xoxb-111-222-FAKESECRET',
        ];

        Http::fake([
            'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'url' => 'https://myteam.slack.com/',
                'team' => 'My Team',
                'user' => 'assistant-bot',
                'team_id' => 'T0123456789',
                'user_id' => 'U0123456789',
                'bot_id' => 'B0123456789',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());
        $this->assertEquals('assistant-bot', $result->getMetadata()['bot_username']);
    }

    public function test_signing_secret_format_validation(): void
    {
        $credentials = [
            'bot_token' => 'xoxb-111-222-FAKESECRET',
            'signing_secret' => '', // Empty signing secret
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('signing_secret', $result->getErrors());
    }

    public function test_http_error_during_auth_test_returns_error(): void
    {
        $credentials = [
            'bot_token' => 'xoxb-111-222-FAKESECRET',
            'signing_secret' => 'abcdef0123456789abcdef0123456789',
        ];

        Http::fake([
            'https://slack.com/api/auth.test' => Http::response(null, 500),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_app_token_with_xapp_prefix_validates_correctly(): void
    {
        // Test various valid app token formats
        $validAppTokens = [
            'xapp-1-AFAKE123456-9999999999999-FAKEAPPSECRETFORTESTING',
            'xapp-2-BFAKE678901-8888888888888-ANOTHERFAKESECRETFORTESTING',
        ];

        foreach ($validAppTokens as $appToken) {
            $credentials = [
                'app_token' => $appToken,
                'bot_token' => 'xoxb-111-222-FAKESECRET',
            ];

            Http::fake([
                'https://slack.com/api/auth.test' => Http::response([
                    'ok' => true,
                    'user' => 'bot',
                    'team_id' => 'T123',
                    'bot_id' => 'B123',
                ], 200),
            ]);

            $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);
            $this->assertTrue($result->isValid(), "App token {$appToken} should be valid");
        }
    }

    public function test_bot_token_with_xoxb_prefix_validates_correctly(): void
    {
        // Test various valid bot token formats
        $validBotTokens = [
            'xoxb-111-222-FAKESECRET',
            'xoxb-333-444-ANOTHERSECRET',
        ];

        foreach ($validBotTokens as $botToken) {
            $credentials = [
                'bot_token' => $botToken,
                'signing_secret' => 'abcdef0123456789abcdef0123456789',
            ];

            Http::fake([
                'https://slack.com/api/auth.test' => Http::response([
                    'ok' => true,
                    'user' => 'bot',
                    'team_id' => 'T123',
                    'bot_id' => 'B123',
                ], 200),
            ]);

            $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);
            $this->assertTrue($result->isValid(), "Bot token {$botToken} should be valid");
        }
    }
}
