<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Validation;

use App\Messenger\Validation\WhatsAppCredentialValidator;
use App\Models\ConnectorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for WhatsAppCredentialValidator.
 *
 * Assumptions:
 * - WhatsApp Cloud API v18+ is used
 * - Webhook mode ONLY (no local mode for WhatsApp)
 * - Access token is a Graph API token (starts with EAA)
 * - Phone number ID is a numeric string
 * - App secret is a 32-character hex string
 *
 * Required Credentials (webhook mode only):
 * - access_token: Graph API access token
 * - phone_number_id: WhatsApp Business Phone Number ID
 * - app_secret: Meta App Secret for webhook signature verification
 * - verify_token: Webhook verification token (user-defined)
 * - webhook_url: Publicly accessible webhook URL
 *
 * Validation is performed in phases:
 * 1. Required fields check
 * 2. Format validation
 * 3. API validation (/me call with access token)
 * 4. Phone number ID validation (/v18.0/{phone_number_id})
 */
class WhatsAppCredentialValidatorTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppCredentialValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new WhatsAppCredentialValidator;
    }

    public function test_requires_access_token(): void
    {
        $credentials = [
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('access_token', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['access_token']));
    }

    public function test_requires_phone_number_id(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('phone_number_id', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['phone_number_id']));
    }

    public function test_requires_app_secret(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('app_secret', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['app_secret']));
    }

    public function test_requires_verify_token(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('verify_token', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['verify_token']));
    }

    public function test_requires_webhook_url(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('webhook_url', $result->getErrors());
        $this->assertStringContainsString('required', strtolower($result->getErrors()['webhook_url']));
    }

    public function test_validates_access_token_via_me_graph_api(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        Http::fake([
            'https://graph.facebook.com/v18.0/me*' => Http::response([
                'id' => '123456789012345',
                'name' => 'Test Business Account',
            ], 200),
            'https://graph.facebook.com/v18.0/123456789012345*' => Http::response([
                'id' => '123456789012345',
                'display_phone_number' => '+1 415 555 1234',
                'verified_name' => 'Test Business',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertTrue($result->isValid());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com/v18.0/me');
        });
    }

    public function test_validates_phone_number_id_via_graph_api(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        Http::fake([
            'https://graph.facebook.com/v18.0/me*' => Http::response([
                'id' => '123456789012345',
                'name' => 'Test Business Account',
            ], 200),
            'https://graph.facebook.com/v18.0/123456789012345*' => Http::response([
                'id' => '123456789012345',
                'display_phone_number' => '+1 415 555 1234',
                'verified_name' => 'Test Business',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertTrue($result->isValid());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com/v18.0/123456789012345');
        });
    }

    public function test_returns_phone_display_name_on_success(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        Http::fake([
            'https://graph.facebook.com/v18.0/me*' => Http::response([
                'id' => '123456789012345',
                'name' => 'Test Business Account',
            ], 200),
            'https://graph.facebook.com/v18.0/123456789012345*' => Http::response([
                'id' => '123456789012345',
                'display_phone_number' => '+1 415 555 1234',
                'verified_name' => 'My Business Name',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertTrue($result->isValid());
        $this->assertEquals('+1 415 555 1234', $result->getMetadata()['display_phone_number']);
        $this->assertEquals('My Business Name', $result->getMetadata()['verified_name']);
    }

    public function test_no_local_mode_validation_path(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        // Local mode should fail for WhatsApp (not supported)
        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_LOCAL);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('mode', $result->getErrors());
        $this->assertStringContainsString('webhook', strtolower($result->getErrors()['mode']));
    }

    public function test_invalid_access_token_returns_validation_error(): void
    {
        $credentials = [
            'access_token' => 'invalid_token',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        Http::fake([
            'https://graph.facebook.com/v18.0/me*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token.',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 400),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('access_token', $result->getErrors());
        $this->assertStringContainsString('Invalid', $result->getErrors()['access_token']);
    }

    public function test_invalid_phone_number_id_returns_validation_error(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '999999999999999',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        Http::fake([
            'https://graph.facebook.com/v18.0/me*' => Http::response([
                'id' => '123456789012345',
                'name' => 'Test Business Account',
            ], 200),
            'https://graph.facebook.com/v18.0/999999999999999*' => Http::response([
                'error' => [
                    'message' => 'Unsupported get request.',
                    'type' => 'GraphMethodException',
                    'code' => 100,
                ],
            ], 400),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('phone_number_id', $result->getErrors());
    }

    public function test_webhook_url_must_be_valid_url(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'not-a-valid-url',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('webhook_url', $result->getErrors());
        $this->assertStringContainsString('valid', strtolower($result->getErrors()['webhook_url']));
    }

    public function test_webhook_url_must_use_https(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'http://example.com/webhook', // HTTP not HTTPS
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('webhook_url', $result->getErrors());
        $this->assertStringContainsString('https', strtolower($result->getErrors()['webhook_url']));
    }

    public function test_phone_number_id_must_be_numeric(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => 'not-numeric-id',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('phone_number_id', $result->getErrors());
        $this->assertStringContainsString('numeric', strtolower($result->getErrors()['phone_number_id']));
    }

    public function test_http_exception_during_validation_returns_error(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-verify-token',
            'webhook_url' => 'https://example.com/webhook',
        ];

        Http::fake([
            'https://graph.facebook.com/v18.0/me*' => Http::response(null, 500),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_all_valid_credentials_pass_validation(): void
    {
        $credentials = [
            'access_token' => 'EAAGtest123456789abcdefghijklmnopqrstuvwxyz',
            'phone_number_id' => '123456789012345',
            'app_secret' => 'abcdef0123456789abcdef0123456789',
            'verify_token' => 'my-custom-verify-token-12345',
            'webhook_url' => 'https://example.com/messenger/webhooks/whatsapp/account-123',
        ];

        Http::fake([
            'https://graph.facebook.com/v18.0/me*' => Http::response([
                'id' => '123456789012345',
                'name' => 'Test Business Account',
            ], 200),
            'https://graph.facebook.com/v18.0/123456789012345*' => Http::response([
                'id' => '123456789012345',
                'display_phone_number' => '+1 415 555 1234',
                'verified_name' => 'Verified Business Name',
            ], 200),
        ]);

        $result = $this->validator->validate($credentials, ConnectorAccount::MODE_WEBHOOK);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
        $this->assertArrayHasKey('display_phone_number', $result->getMetadata());
        $this->assertArrayHasKey('verified_name', $result->getMetadata());
    }
}
