<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Validation;

use App\Messenger\Validation\TelegramCredentialValidator;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for TelegramCredentialValidator.
 *
 * Assumptions:
 * - Telegram bot tokens follow format: numeric_id:alphanumeric_secret
 * - Local mode requires only bot_token
 * - Webhook mode requires bot_token and webhook_url
 * - getMe API validates token and returns bot info
 *
 * Token Format:
 * - Bot tokens: {numeric_bot_id}:{alphanumeric_secret}
 * - Example: 123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789
 */
class TelegramCredentialValidatorTest extends TestCase
{
    use RefreshDatabase;

    private TelegramCredentialValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TelegramCredentialValidator;
    }

    public function test_local_mode_requires_only_bot_token(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'TestBot',
                    'username' => 'test_bot',
                    'can_join_groups' => true,
                    'can_read_all_group_messages' => false,
                    'supports_inline_queries' => false,
                ],
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_local_mode_fails_without_bot_token(): void
    {
        $credentials = [];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('required', $result->getErrors()['bot_token']);
    }

    public function test_webhook_mode_requires_bot_token_and_webhook_url(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
            'webhook_url' => 'https://example.com/webhook/telegram',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'TestBot',
                    'username' => 'test_bot',
                ],
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_webhook_mode_fails_without_webhook_url(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('webhook_url', $result->getErrors());
        $this->assertStringContainsString('required', $result->getErrors()['webhook_url']);
    }

    public function test_webhook_mode_fails_without_bot_token(): void
    {
        $credentials = [
            'webhook_url' => 'https://example.com/webhook/telegram',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
    }

    public function test_token_format_validation_numeric_colon_alphanumeric(): void
    {
        // Valid format: numeric_id:alphanumeric_secret
        $validTokens = [
            '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
            '1:A',
            '9876543210:ABCDEFGHIJKLMNOPQRSTUVWXYZ_-abcdefghijklmnopqrstuvwxyz0123456789',
        ];

        foreach ($validTokens as $token) {
            $credentials = ['bot_token' => $token];

            Http::fake([
                'https://api.telegram.org/bot*/getMe' => Http::response([
                    'ok' => true,
                    'result' => [
                        'id' => 123456789,
                        'is_bot' => true,
                        'first_name' => 'TestBot',
                        'username' => 'test_bot',
                    ],
                ], 200),
            ]);

            $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);
            $this->assertTrue($result->isValid(), "Token '{$token}' should be valid");
        }
    }

    public function test_invalid_token_format_returns_validation_error(): void
    {
        // Test tokens with invalid format (not matching numeric:alphanumeric pattern)
        // Note: Empty string is tested separately as a "required" error
        $invalidTokens = [
            'invalid-token-format',           // No colon
            ':ABCdefGHIjklMNOpqrs',           // No numeric part
            '123456789:',                      // No alphanumeric part
            'abc:123',                         // Non-numeric before colon
            '123456789',                       // No colon at all
        ];

        foreach ($invalidTokens as $token) {
            $credentials = ['bot_token' => $token];

            $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

            $this->assertFalse($result->isValid(), "Token '{$token}' should be invalid");
            $this->assertArrayHasKey('bot_token', $result->getErrors());
            $this->assertStringContainsString('format', strtolower($result->getErrors()['bot_token']));
        }
    }

    public function test_empty_bot_token_returns_required_error(): void
    {
        $credentials = ['bot_token' => ''];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['bot_token']));
    }

    public function test_get_me_api_call_for_validation(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'MyAwesomeBot',
                    'username' => 'my_awesome_bot',
                ],
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());

        // Verify API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'getMe');
        });
    }

    public function test_returns_bot_username_and_id_on_success(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 987654321,
                    'is_bot' => true,
                    'first_name' => 'ProductionBot',
                    'username' => 'production_bot',
                    'can_join_groups' => true,
                ],
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());
        $this->assertEquals('production_bot', $result->getMetadata()['bot_username']);
        $this->assertEquals(987654321, $result->getMetadata()['bot_id']);
    }

    public function test_get_me_api_failure_returns_validation_error(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => false,
                'error_code' => 401,
                'description' => 'Unauthorized',
            ], 200), // Telegram returns 200 with ok=false for API errors
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('Unauthorized', $result->getErrors()['bot_token']);
    }

    public function test_http_error_during_get_me_returns_error(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response(null, 500),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_webhook_url_format_validation(): void
    {
        // Invalid webhook URLs
        $invalidUrls = [
            'not-a-url',                      // Not a URL
            'http://example.com/webhook',     // Not HTTPS
            'ftp://example.com/webhook',      // Wrong protocol
            '',                                // Empty
        ];

        foreach ($invalidUrls as $url) {
            $credentials = [
                'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
                'webhook_url' => $url,
            ];

            $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

            $this->assertFalse($result->isValid(), "URL '{$url}' should be invalid");
            $this->assertArrayHasKey('webhook_url', $result->getErrors());
        }
    }

    public function test_valid_webhook_url_formats(): void
    {
        $validUrls = [
            'https://example.com/webhook',
            'https://subdomain.example.com/api/telegram/webhook',
            'https://example.com:443/webhook',
            'https://example.com/webhook?token=abc',
        ];

        foreach ($validUrls as $url) {
            $credentials = [
                'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
                'webhook_url' => $url,
            ];

            Http::fake([
                'https://api.telegram.org/bot*/getMe' => Http::response([
                    'ok' => true,
                    'result' => [
                        'id' => 123456789,
                        'is_bot' => true,
                        'first_name' => 'TestBot',
                        'username' => 'test_bot',
                    ],
                ], 200),
            ]);

            $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

            $this->assertTrue($result->isValid(), "URL '{$url}' should be valid");
        }
    }

    public function test_returns_first_name_in_metadata(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'My Assistant Bot',
                    'username' => 'assistant_bot',
                ],
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertTrue($result->isValid());
        $this->assertEquals('My Assistant Bot', $result->getMetadata()['bot_first_name']);
    }

    public function test_validates_bot_is_actually_a_bot(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => false, // Not a bot!
                    'first_name' => 'Regular User',
                    'username' => 'regular_user',
                ],
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertStringContainsString('not a bot', strtolower($result->getErrors()['bot_token']));
    }

    public function test_multiple_field_errors_returned(): void
    {
        $credentials = [
            'bot_token' => 'invalid-format',
            // Missing webhook_url for webhook mode
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->getErrors());
        $this->assertArrayHasKey('bot_token', $result->getErrors());
        $this->assertArrayHasKey('webhook_url', $result->getErrors());
    }

    public function test_network_timeout_returns_validation_error(): void
    {
        $credentials = [
            'bot_token' => '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789',
        ];

        Http::fake([
            'https://api.telegram.org/bot*/getMe' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }
}
