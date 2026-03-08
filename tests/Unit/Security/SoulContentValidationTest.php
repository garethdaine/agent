<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\DelegateeProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoulContentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_soul_rejects_api_key_patterns(): void
    {
        $profile = DelegateeProfile::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('credential-like content');

        $profile->setSoul([
            'personality' => 'Friendly assistant with key sk-abc123def456ghi789jkl012mno345pqr678',
        ]);
    }

    public function test_set_soul_rejects_aws_key_patterns(): void
    {
        $profile = DelegateeProfile::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $profile->setSoul([
            'user_context' => 'AWS credentials: AKIAIOSFODNN7EXAMPLE',
        ]);
    }

    public function test_set_soul_rejects_password_patterns(): void
    {
        $profile = DelegateeProfile::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $profile->setSoul([
            'system_prompt' => 'Connect with password=s3cretP@ss',
        ]);
    }

    public function test_set_soul_rejects_private_key_patterns(): void
    {
        $profile = DelegateeProfile::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $profile->setSoul([
            'personality' => '-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQ...',
        ]);
    }

    public function test_set_soul_rejects_oversized_content(): void
    {
        $profile = DelegateeProfile::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds maximum length');

        $profile->setSoul([
            'personality' => str_repeat('a', 10001),
        ]);
    }

    public function test_set_soul_accepts_valid_content(): void
    {
        $profile = DelegateeProfile::factory()->create();

        $profile->setSoul([
            'personality' => 'I am a helpful coding assistant.',
            'user_context' => 'The user prefers concise responses.',
        ]);

        $profile->refresh();
        $soul = $profile->getSoul();
        $this->assertSame('I am a helpful coding assistant.', $soul['personality']);
        $this->assertSame('The user prefers concise responses.', $soul['user_context']);
    }

    public function test_set_soul_accepts_null_values(): void
    {
        $profile = DelegateeProfile::factory()->create();

        $profile->setSoul([
            'personality' => null,
            'system_prompt' => null,
            'user_context' => null,
        ]);

        $profile->refresh();
        $this->assertNull($profile->soul_json);
    }
}
