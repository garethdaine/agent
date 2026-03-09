<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Support\Agent\OutputRedactor;
use Tests\TestCase;

class OutputRedactorTest extends TestCase
{
    private OutputRedactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new OutputRedactor;
    }

    public function test_redacts_private_key(): void
    {
        $input = "-----BEGIN RSA PRIVATE KEY-----\nMIIEpA...\n-----END RSA PRIVATE KEY-----";
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertSame('[REDACTED_PRIVATE_KEY]', $result);
        $this->assertSame(1, $count);
    }

    public function test_redacts_bearer_token(): void
    {
        $input = 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test';
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertStringContainsString('[REDACTED_BEARER_TOKEN]', $result);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_redacts_api_key(): void
    {
        $input = 'Key: sk-1234567890abcdefghijklmnopqr';
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertStringContainsString('[REDACTED_API_KEY]', $result);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_redacts_aws_key(): void
    {
        $input = 'AWS Key: AKIAIOSFODNN7EXAMPLE';
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertStringContainsString('[REDACTED_AWS_KEY]', $result);
        $this->assertSame(1, $count);
    }

    public function test_redacts_password(): void
    {
        $input = 'password=mysecretpassword123';
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertStringContainsString('[REDACTED_PASSWORD]', $result);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_redacts_email(): void
    {
        $input = 'Contact: user@example.com';
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result);
        $this->assertSame(1, $count);
    }

    public function test_does_not_redact_safe_content(): void
    {
        $input = 'This is a normal log message with no sensitive data.';
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertSame($input, $result);
        $this->assertSame(0, $count);
    }

    public function test_multiple_redactions(): void
    {
        $input = 'user@test.com password=secret123 token=abc123';
        $count = 0;
        $this->redactor->redact($input, $count);
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_is_binary_chunk_detects_binary(): void
    {
        $binary = str_repeat("\x00\x01\x02\x03\xFF", 100);
        $this->assertTrue($this->redactor->isBinaryChunk($binary));
    }

    public function test_is_binary_chunk_allows_text(): void
    {
        $this->assertFalse($this->redactor->isBinaryChunk('This is regular text output'));
    }

    public function test_is_binary_chunk_empty_string(): void
    {
        $this->assertFalse($this->redactor->isBinaryChunk(''));
    }

    public function test_redacts_api_key_equals_format(): void
    {
        $input = 'api_key=sk-abc123456789';
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertStringContainsString('[REDACTED', $result);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_redacts_secret_token(): void
    {
        $input = 'secret=my_super_secret_value';
        $count = 0;
        $result = $this->redactor->redact($input, $count);
        $this->assertStringContainsString('[REDACTED]', $result);
        $this->assertSame(1, $count);
    }
}
