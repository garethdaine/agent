<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\DTOs\Security\ExfiltrationInspectionResult;
use App\Enums\Security\ExfiltrationPattern;
use App\Services\Security\ExfiltrationDetector;
use App\Services\Security\SecurityConfigProvider;
use App\Services\Security\SecurityEventLogger;
use PHPUnit\Framework\TestCase;

class ExfiltrationDetectorTest extends TestCase
{
    private ExfiltrationDetector $detector;

    private SecurityConfigProvider $config;

    private SecurityEventLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(SecurityConfigProvider::class);
        $this->logger = $this->createMock(SecurityEventLogger::class);
        $this->detector = new ExfiltrationDetector($this->config, $this->logger, allowedHosts: ['safe-api.com']);
    }

    public function test_session_token_echo_with_session_id_detected(): void
    {
        $body = 'some data session_id=abc123xyz789longtoken more data';

        $this->assertTrue(
            $this->detector->inspectPattern(ExfiltrationPattern::SessionTokenEcho, $body)
        );
    }

    public function test_session_token_echo_with_bearer_token_detected(): void
    {
        $body = 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJ0ZXN0In0.abc123';

        $this->assertTrue(
            $this->detector->inspectPattern(ExfiltrationPattern::SessionTokenEcho, $body)
        );
    }

    public function test_conversation_reflection_detected(): void
    {
        $body = json_encode([
            ['role' => 'user', 'content' => 'hello'],
            ['role' => 'assistant', 'content' => 'hi'],
            ['role' => 'user', 'content' => 'how are you'],
            ['role' => 'assistant', 'content' => 'good'],
        ]);

        $this->assertTrue(
            $this->detector->inspectPattern(ExfiltrationPattern::ConversationReflection, $body)
        );
    }

    public function test_system_prompt_leak_detected(): void
    {
        $body = 'Here is the system_prompt: You are a helpful assistant that...';

        $this->assertTrue(
            $this->detector->inspectPattern(ExfiltrationPattern::SystemPromptLeak, $body)
        );
    }

    public function test_credential_pattern_sk_key_detected(): void
    {
        $body = 'api_key: sk-abcdef1234567890abcdef';

        $this->assertTrue(
            $this->detector->inspectPattern(ExfiltrationPattern::CredentialPattern, $body)
        );
    }

    public function test_credential_pattern_private_key_detected(): void
    {
        $body = '-----BEGIN RSA PRIVATE KEY-----\nMIIE...base64data...==\n-----END RSA PRIVATE KEY-----';

        $this->assertTrue(
            $this->detector->inspectPattern(ExfiltrationPattern::CredentialPattern, $body)
        );
    }

    public function test_credential_pattern_github_token_detected(): void
    {
        $body = 'token=ghp_ABCDEFghijklmnop1234567890abcdef1234';

        $this->assertTrue(
            $this->detector->inspectPattern(ExfiltrationPattern::CredentialPattern, $body)
        );
    }

    public function test_pii_echo_with_email_detected(): void
    {
        $body = 'Please send to user@example.com and admin@company.org for review';

        $this->assertTrue(
            $this->detector->inspectPattern(ExfiltrationPattern::PiiEcho, $body)
        );
    }

    public function test_get_request_not_inspected(): void
    {
        $result = $this->detector->inspect('GET', 'https://evil.com/exfil', 'session_id=abc123xyz789longtoken');

        $this->assertFalse($result->blocked);
        $this->assertEmpty($result->matchedPatterns);
    }

    public function test_post_to_allowlisted_host_not_inspected(): void
    {
        $result = $this->detector->inspect('POST', 'https://safe-api.com/data', 'session_id=abc123xyz789longtoken');

        $this->assertFalse($result->blocked);
        $this->assertEmpty($result->matchedPatterns);
    }

    public function test_post_with_clean_body_not_blocked(): void
    {
        $result = $this->detector->inspect(
            'POST',
            'https://external.com/api',
            'This is just a normal request with some data.',
        );

        $this->assertFalse($result->blocked);
        $this->assertEmpty($result->matchedPatterns);
    }

    public function test_each_pattern_independently_testable(): void
    {
        foreach (ExfiltrationPattern::cases() as $pattern) {
            $result = $this->detector->inspectPattern($pattern, 'clean content with no patterns');
            $this->assertIsBool($result);
        }
    }

    public function test_exfiltration_inspection_result_dto_fields_accessible(): void
    {
        $result = new ExfiltrationInspectionResult(
            blocked: true,
            matchedPatterns: [ExfiltrationPattern::CredentialPattern],
            reason: 'Credential detected in outbound request',
        );

        $this->assertTrue($result->blocked);
        $this->assertCount(1, $result->matchedPatterns);
        $this->assertSame(ExfiltrationPattern::CredentialPattern, $result->matchedPatterns[0]);
        $this->assertSame('Credential detected in outbound request', $result->reason);
    }

    public function test_post_with_credential_to_external_host_is_blocked(): void
    {
        $this->logger->expects($this->once())
            ->method('logExfiltrationAttempt');

        $result = $this->detector->inspect(
            'POST',
            'https://evil.com/collect',
            'sending api key sk-abcdef1234567890abcdef to external service',
            'session-123',
        );

        $this->assertTrue($result->blocked);
        $this->assertContains(ExfiltrationPattern::CredentialPattern, $result->matchedPatterns);
    }

    public function test_put_request_is_inspected(): void
    {
        $result = $this->detector->inspect(
            'PUT',
            'https://evil.com/update',
            'token=ghp_ABCDEFghijklmnop1234567890abcdef1234',
        );

        $this->assertTrue($result->blocked);
    }

    public function test_patch_request_is_inspected(): void
    {
        $result = $this->detector->inspect(
            'PATCH',
            'https://evil.com/patch',
            '-----BEGIN RSA PRIVATE KEY-----\ndata\n-----END RSA PRIVATE KEY-----',
        );

        $this->assertTrue($result->blocked);
    }

    public function test_null_body_not_blocked(): void
    {
        $result = $this->detector->inspect('POST', 'https://evil.com/endpoint', null);

        $this->assertFalse($result->blocked);
    }

    public function test_empty_body_not_blocked(): void
    {
        $result = $this->detector->inspect('POST', 'https://evil.com/endpoint', '');

        $this->assertFalse($result->blocked);
    }
}
