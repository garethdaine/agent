<?php

declare(strict_types=1);

namespace Tests\Unit\Messenger\Validation;

use App\Messenger\Validation\IngressProbe;
use App\Messenger\Validation\ProbeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for IngressProbe webhook validation service.
 *
 * Assumptions:
 * - Webhook URLs must be HTTPS with valid TLS certificates
 * - Each provider has specific verification requirements
 * - Probe returns actionable diagnostics on failure
 *
 * Test Scenarios:
 * 1. Basic reachability (HTTP HEAD, 2xx response)
 * 2. TLS certificate validation (not expired)
 * 3. TLS expiry warning if <7 days
 * 4. Slack URL verification challenge
 * 5. Telegram setWebhook confirmation
 * 6. Discord PING/PONG verification
 * 7. WhatsApp verify_token validation
 * 8. Actionable error messages on failure
 */
class IngressProbeTest extends TestCase
{
    use RefreshDatabase;

    private IngressProbe $probe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->probe = new IngressProbe;
    }

    // =========================================================================
    // Basic Reachability Tests
    // =========================================================================

    public function test_basic_reachability_check_succeeds_with_2xx_response(): void
    {
        Http::fake([
            'https://example.com/webhook*' => Http::response('OK', 200),
        ]);

        $result = $this->probe->checkReachability('https://example.com/webhook');

        $this->assertTrue($result->success);
        $this->assertNull($result->error);
    }

    public function test_basic_reachability_check_fails_with_connection_error(): void
    {
        Http::fake(function ($request) {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $result = $this->probe->checkReachability('https://unreachable.example.com/webhook');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('reachable', strtolower($result->diagnostic));
    }

    public function test_basic_reachability_check_fails_with_5xx_response(): void
    {
        Http::fake([
            'https://example.com/webhook*' => Http::response('Server Error', 500),
        ]);

        $result = $this->probe->checkReachability('https://example.com/webhook');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->diagnostic);
    }

    public function test_basic_reachability_check_accepts_404_as_reachable(): void
    {
        // 404 means the server is reachable but the path doesn't exist yet
        // This is acceptable for webhooks that haven't been configured
        Http::fake([
            'https://example.com/webhook*' => Http::response('Not Found', 404),
        ]);

        $result = $this->probe->checkReachability('https://example.com/webhook');

        $this->assertTrue($result->success);
    }

    // =========================================================================
    // TLS Certificate Validation Tests
    // =========================================================================

    public function test_tls_validation_passes_with_valid_certificate(): void
    {
        // Mock successful TLS check via stream context
        $result = $this->probe->checkTls('https://example.com/webhook', [
            'valid_from' => time() - 86400,
            'valid_to' => time() + (30 * 86400), // 30 days from now
        ]);

        $this->assertTrue($result->success);
        $this->assertNull($result->warning);
    }

    public function test_tls_validation_fails_with_expired_certificate(): void
    {
        $result = $this->probe->checkTls('https://example.com/webhook', [
            'valid_from' => time() - (60 * 86400), // 60 days ago
            'valid_to' => time() - 86400, // Expired yesterday
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('expired', strtolower($result->diagnostic));
        $this->assertStringContainsString('renew', strtolower($result->diagnostic));
    }

    public function test_tls_validation_warns_if_expiring_within_7_days(): void
    {
        $result = $this->probe->checkTls('https://example.com/webhook', [
            'valid_from' => time() - 86400,
            'valid_to' => time() + (5 * 86400), // 5 days from now
        ]);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->warning);
        $this->assertStringContainsString('expir', strtolower($result->warning));
        $this->assertStringContainsString('5 days', strtolower($result->warning));
    }

    public function test_tls_validation_fails_with_certificate_not_yet_valid(): void
    {
        $result = $this->probe->checkTls('https://example.com/webhook', [
            'valid_from' => time() + (7 * 86400), // 7 days in the future
            'valid_to' => time() + (30 * 86400),
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not yet valid', strtolower($result->diagnostic));
    }

    // =========================================================================
    // Slack URL Verification Tests
    // =========================================================================

    public function test_slack_url_verification_succeeds_when_challenge_echoed(): void
    {
        Http::fake([
            'https://example.com/webhook/slack*' => function ($request) {
                $body = $request->data();
                if (($body['type'] ?? '') === 'url_verification') {
                    return Http::response(['challenge' => $body['challenge']], 200);
                }

                return Http::response('OK', 200);
            },
        ]);

        $result = $this->probe->verifySlack('https://example.com/webhook/slack');

        $this->assertTrue($result->success);
    }

    public function test_slack_url_verification_fails_when_challenge_not_echoed(): void
    {
        Http::fake([
            'https://example.com/webhook/slack*' => Http::response(['challenge' => 'wrong-value'], 200),
        ]);

        $result = $this->probe->verifySlack('https://example.com/webhook/slack');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('challenge', strtolower($result->diagnostic));
        $this->assertStringContainsString('echo', strtolower($result->diagnostic));
    }

    public function test_slack_url_verification_fails_with_timeout(): void
    {
        Http::fake(function ($request) {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $result = $this->probe->verifySlack('https://example.com/webhook/slack');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('3 seconds', strtolower($result->diagnostic));
    }

    // =========================================================================
    // Telegram setWebhook Tests
    // =========================================================================

    public function test_telegram_setwebhook_succeeds_with_ok_response(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/setWebhook*' => Http::response([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was set',
            ], 200),
        ]);

        $result = $this->probe->verifyTelegram(
            'https://example.com/webhook/telegram',
            ['bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11']
        );

        $this->assertTrue($result->success);
    }

    public function test_telegram_setwebhook_fails_with_invalid_token(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/setWebhook*' => Http::response([
                'ok' => false,
                'error_code' => 401,
                'description' => 'Unauthorized',
            ], 401),
        ]);

        $result = $this->probe->verifyTelegram(
            'https://example.com/webhook/telegram',
            ['bot_token' => 'invalid-token']
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unauthorized', $result->diagnostic);
    }

    public function test_telegram_setwebhook_fails_with_bad_url(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*/setWebhook*' => Http::response([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: bad webhook: HTTPS url must be provided for webhook',
            ], 400),
        ]);

        $result = $this->probe->verifyTelegram(
            'http://example.com/webhook/telegram', // HTTP instead of HTTPS
            ['bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11']
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('HTTPS', $result->diagnostic);
    }

    // =========================================================================
    // Discord PING/PONG Tests
    // =========================================================================

    public function test_discord_ping_pong_succeeds_with_pong_response(): void
    {
        Http::fake([
            'https://example.com/webhook/discord*' => function ($request) {
                $body = json_decode($request->body(), true);
                if (($body['type'] ?? 0) === 1) { // PING
                    return Http::response(['type' => 1], 200); // PONG
                }

                return Http::response('OK', 200);
            },
        ]);

        $result = $this->probe->verifyDiscord(
            'https://example.com/webhook/discord',
            ['public_key' => str_repeat('a', 64)]
        );

        $this->assertTrue($result->success);
    }

    public function test_discord_ping_pong_fails_without_pong_response(): void
    {
        Http::fake([
            'https://example.com/webhook/discord*' => Http::response(['type' => 4], 200), // Wrong type
        ]);

        $result = $this->probe->verifyDiscord(
            'https://example.com/webhook/discord',
            ['public_key' => str_repeat('a', 64)]
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('PONG', $result->diagnostic);
    }

    public function test_discord_ping_pong_returns_guidance_for_signature_error(): void
    {
        // Discord 401 is expected behavior for unsigned test requests.
        // The endpoint correctly rejects unsigned requests, which means
        // signature verification is working. This is non-blocking guidance.
        Http::fake([
            'https://example.com/webhook/discord*' => Http::response([
                'code' => 0,
                'message' => 'Invalid request signature',
            ], 401),
        ]);

        $result = $this->probe->verifyDiscord(
            'https://example.com/webhook/discord',
            ['public_key' => str_repeat('a', 64)]
        );

        // 401 should return success=true with guidance, not failure
        $this->assertTrue($result->success);
        $this->assertNotNull($result->diagnostic);
        $this->assertStringContainsString('401', $result->diagnostic);
        $this->assertStringContainsString('verification', strtolower($result->diagnostic));
    }

    // =========================================================================
    // WhatsApp Verify Token Tests
    // =========================================================================

    public function test_whatsapp_verify_token_validation_succeeds(): void
    {
        // WhatsApp verification happens on Meta's side during webhook setup
        // We validate that the verify_token is configured and provide the URL
        $result = $this->probe->verifyWhatsApp([
            'verify_token' => 'my-secret-token',
            'phone_number_id' => '123456789',
        ]);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('verify_token', $result->metadata);
    }

    public function test_whatsapp_verify_token_validation_fails_without_token(): void
    {
        $result = $this->probe->verifyWhatsApp([
            'phone_number_id' => '123456789',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('verify_token', strtolower($result->diagnostic));
    }

    // =========================================================================
    // Full Probe Integration Tests
    // =========================================================================

    public function test_full_probe_runs_all_checks_for_slack(): void
    {
        Http::fake([
            'https://example.com/webhook/slack*' => function ($request) {
                $body = $request->data();
                if (($body['type'] ?? '') === 'url_verification') {
                    return Http::response(['challenge' => $body['challenge']], 200);
                }

                return Http::response('OK', 200);
            },
        ]);

        $result = $this->probe->probe(
            'https://example.com/webhook/slack',
            'slack',
            [],
            ['skip_tls' => true] // Skip TLS in test environment
        );

        $this->assertTrue($result->success);
    }

    public function test_full_probe_runs_all_checks_for_telegram(): void
    {
        Http::fake([
            'https://example.com/webhook/telegram*' => Http::response('OK', 200),
            'https://api.telegram.org/bot*/setWebhook*' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $result = $this->probe->probe(
            'https://example.com/webhook/telegram',
            'telegram',
            ['bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11'],
            ['skip_tls' => true]
        );

        $this->assertTrue($result->success);
    }

    public function test_full_probe_runs_all_checks_for_discord(): void
    {
        Http::fake([
            'https://example.com/webhook/discord*' => function ($request) {
                $body = json_decode($request->body(), true);
                if (($body['type'] ?? 0) === 1) {
                    return Http::response(['type' => 1], 200);
                }

                return Http::response('OK', 200);
            },
        ]);

        $result = $this->probe->probe(
            'https://example.com/webhook/discord',
            'discord',
            ['public_key' => str_repeat('a', 64)],
            ['skip_tls' => true]
        );

        $this->assertTrue($result->success);
    }

    public function test_full_probe_skips_provider_verification_for_whatsapp(): void
    {
        Http::fake([
            'https://example.com/webhook/whatsapp*' => Http::response('OK', 200),
        ]);

        // WhatsApp verification happens on Meta's side - we just validate credentials
        $result = $this->probe->probe(
            'https://example.com/webhook/whatsapp',
            'whatsapp',
            [
                'verify_token' => 'my-secret-token',
                'phone_number_id' => '123456789',
            ],
            ['skip_tls' => true]
        );

        $this->assertTrue($result->success);
    }

    // =========================================================================
    // Actionable Error Messages Tests
    // =========================================================================

    public function test_connection_error_provides_actionable_diagnostic(): void
    {
        Http::fake(function ($request) {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $result = $this->probe->checkReachability('https://example.com/webhook');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('firewall', strtolower($result->diagnostic));
    }

    public function test_tls_error_provides_renewal_guidance(): void
    {
        $result = $this->probe->checkTls('https://example.com/webhook', [
            'valid_from' => time() - (60 * 86400),
            'valid_to' => time() - 86400, // Expired
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('renew', strtolower($result->diagnostic));
    }

    public function test_slack_verification_error_provides_implementation_guidance(): void
    {
        Http::fake([
            'https://example.com/webhook/slack*' => Http::response(['wrong' => 'response'], 200),
        ]);

        $result = $this->probe->verifySlack('https://example.com/webhook/slack');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('endpoint', strtolower($result->diagnostic));
    }

    public function test_probe_result_has_success_factory_method(): void
    {
        $result = ProbeResult::success();

        $this->assertTrue($result->success);
        $this->assertNull($result->error);
        $this->assertNull($result->diagnostic);
    }

    public function test_probe_result_has_failure_factory_method(): void
    {
        $result = ProbeResult::failure('Webhook validation failed', 'Check your configuration');

        $this->assertFalse($result->success);
        $this->assertEquals('Webhook validation failed', $result->error);
        $this->assertEquals('Check your configuration', $result->diagnostic);
    }

    public function test_probe_result_has_warning_factory_method(): void
    {
        $result = ProbeResult::successWithWarning('Certificate expiring in 5 days');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->warning);
        $this->assertStringContainsString('5 days', $result->warning);
    }
}
