<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Services\Security\CredentialRedactor;
use Tests\TestCase;

class CredentialRedactorTest extends TestCase
{
    private CredentialRedactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new CredentialRedactor;
    }

    public function test_redacts_json_password_value(): void
    {
        $content = '{"username":"user@test.com","password":"s3cr3tP@ss!"}';
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('s3cr3tP@ss!', $result['content']);
        $this->assertStringContainsString('"password":"[REDACTED]"', $result['content']);
        $this->assertGreaterThan(0, $result['redactions']);
    }

    public function test_redacts_json_api_key_value(): void
    {
        $content = '{"api_key":"sk-abc123xyz789","provider":"openai"}';
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('sk-abc123xyz789', $result['content']);
        $this->assertStringContainsString('"api_key":"[REDACTED]"', $result['content']);
    }

    public function test_redacts_json_bearer_token(): void
    {
        $content = '{"bearer_token":"eyJhbGci.token.here","type":"oauth"}';
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('eyJhbGci.token.here', $result['content']);
    }

    public function test_redacts_markdown_bold_password(): void
    {
        $content = "Here are the credentials:\n\n- **Email:** user@test.com\n- **Password:** dE3psS!@7J6aswwO";
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('dE3psS!@7J6aswwO', $result['content']);
        $this->assertStringContainsString('[REDACTED]', $result['content']);
        $this->assertGreaterThan(0, $result['redactions']);
    }

    public function test_redacts_markdown_bold_api_key(): void
    {
        $content = "**API Key:** sk-proj-abc123-xyz789\n**API Secret:** mySecret456";
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('sk-proj-abc123-xyz789', $result['content']);
        $this->assertStringNotContainsString('mySecret456', $result['content']);
    }

    public function test_redacts_backtick_wrapped_credentials(): void
    {
        $content = "**Password:** `dE3psS!@7J6aswwO`\n**Token:** `abc123xyz`";
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('dE3psS!@7J6aswwO', $result['content']);
        $this->assertStringContainsString('`[REDACTED]`', $result['content']);
    }

    public function test_redacts_plain_label_password(): void
    {
        $content = "Password: mySecretPassword123\nUsername: admin";
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('mySecretPassword123', $result['content']);
        $this->assertStringContainsString('Password: [REDACTED]', $result['content']);
    }

    public function test_redacts_plain_label_with_bullet(): void
    {
        $content = "- Password: s3cr3t\n- API Key: sk-123";
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('s3cr3t', $result['content']);
        $this->assertStringNotContainsString('sk-123', $result['content']);
    }

    public function test_redacts_env_var_assignment(): void
    {
        $content = 'PASSWORD=mySecret123 API_KEY=sk-abc123';
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('mySecret123', $result['content']);
        $this->assertStringContainsString('PASSWORD=[REDACTED]', $result['content']);
    }

    public function test_preserves_variable_references(): void
    {
        $content = 'PASSWORD=$VAULT_PASSWORD API_KEY=$API_KEY_VAR';
        $result = $this->redactor->redact($content);

        // Variable references ($...) should NOT be redacted
        $this->assertStringContainsString('$VAULT_PASSWORD', $result['content']);
        $this->assertStringContainsString('$API_KEY_VAR', $result['content']);
    }

    public function test_no_redaction_for_safe_content(): void
    {
        $content = "I opened x.com and navigated to the login page.\nThe page shows a sign-in form.";
        $result = $this->redactor->redact($content);

        $this->assertSame($content, $result['content']);
        $this->assertSame(0, $result['redactions']);
    }

    public function test_does_not_redact_already_redacted_values(): void
    {
        $content = "**Password:** [REDACTED]\n**Token:** [REDACTED]";
        $result = $this->redactor->redact($content);

        $this->assertSame(0, $result['redactions']);
    }

    public function test_redacts_multiple_credential_types(): void
    {
        $content = implode("\n", [
            '{"password":"secret1","api_key":"key1","access_token":"tok1"}',
        ]);
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('secret1', $result['content']);
        $this->assertStringNotContainsString('key1', $result['content']);
        $this->assertStringNotContainsString('tok1', $result['content']);
        $this->assertSame(3, $result['redactions']);
    }

    public function test_redacts_totp_secret(): void
    {
        $content = '**TOTP Secret:** JBSWY3DPEHPK3PXP';
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $result['content']);
    }

    public function test_redacts_private_key(): void
    {
        $content = '"private_key":"-----BEGIN RSA PRIVATE KEY-----"';
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $result['content']);
    }

    public function test_redacts_client_secret(): void
    {
        $content = "Client Secret: abc-def-ghi-jkl\nClient ID: 12345";
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('abc-def-ghi-jkl', $result['content']);
    }

    public function test_skips_long_descriptions(): void
    {
        // Long values (> 6 words) are likely descriptions, not credential values
        $content = 'Password: This is the password reset instructions page for the user';
        $result = $this->redactor->redact($content);

        $this->assertSame(0, $result['redactions']);
    }

    public function test_case_insensitive_matching(): void
    {
        $content = "PASSWORD: secret1\nApi_Key: key1\nBEARER_TOKEN: tok1";
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('secret1', $result['content']);
        $this->assertStringNotContainsString('key1', $result['content']);
        $this->assertStringNotContainsString('tok1', $result['content']);
    }

    public function test_real_world_discord_exposure_scenario(): void
    {
        // Matches the exact scenario from the bug: agent displaying vault credentials in Discord
        $content = "A headed browser window should now be open on your machine pointed at the X login page. Please log in manually using:\n\n- **Email:** axiomsparkai@gmail.com\n- **Password:** dE3psS!@7J6aswwO\n\nOnce you're logged in and can see the X home feed, let me know and I'll take over to post.";
        $result = $this->redactor->redact($content);

        $this->assertStringNotContainsString('dE3psS!@7J6aswwO', $result['content']);
        $this->assertStringContainsString('[REDACTED]', $result['content']);
        $this->assertGreaterThan(0, $result['redactions']);
    }
}
