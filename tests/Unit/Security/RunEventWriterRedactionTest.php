<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Support\Agent\OutputRedactor;
use Tests\TestCase;

class RunEventWriterRedactionTest extends TestCase
{
    private function invokeRedact(string $payload): string
    {
        $redactor = new OutputRedactor;
        $count = 0;

        return $redactor->redact($payload, $count);
    }

    public function test_email_addresses_are_redacted(): void
    {
        $output = 'Contact user@example.com for support';
        $result = $this->invokeRedact($output);

        $this->assertStringNotContainsString('user@example.com', $result);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result);
    }

    public function test_openai_api_keys_are_redacted(): void
    {
        $output = 'Using key sk-abc123def456ghi789jkl012mno345pqr678';
        $result = $this->invokeRedact($output);

        $this->assertStringNotContainsString('sk-abc123def456ghi789jkl012mno345pqr678', $result);
        $this->assertStringContainsString('[REDACTED_API_KEY]', $result);
    }

    public function test_aws_access_keys_are_redacted(): void
    {
        $output = 'AWS key: AKIAIOSFODNN7EXAMPLE';
        $result = $this->invokeRedact($output);

        $this->assertStringNotContainsString('AKIAIOSFODNN7EXAMPLE', $result);
        $this->assertStringContainsString('[REDACTED_AWS_KEY]', $result);
    }

    public function test_bearer_tokens_are_redacted(): void
    {
        $output = 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.payload.signature';
        $result = $this->invokeRedact($output);

        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $result);
        $this->assertStringContainsString('[REDACTED_BEARER_TOKEN]', $result);
    }

    public function test_password_values_are_redacted(): void
    {
        $output = 'password=s3cret123';
        $result = $this->invokeRedact($output);

        $this->assertStringNotContainsString('s3cret123', $result);
        $this->assertStringContainsString('[REDACTED_PASSWORD]', $result);
    }

    public function test_clean_output_unchanged(): void
    {
        $output = 'Build completed successfully. 42 tests passed.';
        $result = $this->invokeRedact($output);

        $this->assertSame($output, $result);
    }

    public function test_multiple_pii_types_redacted_in_single_output(): void
    {
        $output = 'Email: admin@corp.com, Key: sk-abcdefghijklmnopqrstuvwxyz, Auth: Bearer abc.def.ghi';
        $result = $this->invokeRedact($output);

        $this->assertStringNotContainsString('admin@corp.com', $result);
        $this->assertStringNotContainsString('sk-abcdefghijklmnopqrstuvwxyz', $result);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result);
        $this->assertStringContainsString('[REDACTED_API_KEY]', $result);
        $this->assertStringContainsString('[REDACTED_BEARER_TOKEN]', $result);
    }
}
