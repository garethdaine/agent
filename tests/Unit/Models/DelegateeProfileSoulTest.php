<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DelegateeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateeProfileSoulTest extends TestCase
{
    use RefreshDatabase;

    private DelegateeProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->profile = DelegateeProfile::factory()->create([
            'user_id' => $user->id,
        ]);
    }

    public function test_get_soul_returns_defaults_when_null(): void
    {
        $this->assertNull($this->profile->soul_json);

        $soul = $this->profile->getSoul();

        $this->assertIsArray($soul);
        $this->assertNull($soul['personality']);
        $this->assertNull($soul['system_prompt']);
        $this->assertNull($soul['user_context']);
    }

    public function test_set_soul_persists_values(): void
    {
        $this->profile->setSoul([
            'personality' => 'Friendly and thorough',
            'system_prompt' => 'You are a testing assistant.',
            'user_context' => 'PHP project using Laravel.',
        ]);

        $this->profile->refresh();

        $soul = $this->profile->getSoul();
        $this->assertEquals('Friendly and thorough', $soul['personality']);
        $this->assertEquals('You are a testing assistant.', $soul['system_prompt']);
        $this->assertEquals('PHP project using Laravel.', $soul['user_context']);
    }

    public function test_set_soul_filters_empty_values(): void
    {
        $this->profile->setSoul([
            'personality' => 'Meticulous',
            'system_prompt' => '',
            'user_context' => null,
        ]);

        $this->profile->refresh();

        $this->assertNotNull($this->profile->soul_json);
        $this->assertEquals('Meticulous', $this->profile->soul_json['personality']);
        $this->assertArrayNotHasKey('system_prompt', $this->profile->soul_json);
        $this->assertArrayNotHasKey('user_context', $this->profile->soul_json);
    }

    public function test_set_soul_sets_null_when_all_empty(): void
    {
        $this->profile->setSoul([
            'personality' => '',
            'system_prompt' => '',
            'user_context' => '',
        ]);

        $this->profile->refresh();

        $this->assertNull($this->profile->soul_json);
    }

    public function test_set_soul_rejects_api_key_in_personality(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('credential-like content');

        $this->profile->setSoul([
            'personality' => 'Use this key: sk-abc123def456ghi789jkl012mno345pqr678',
        ]);
    }

    public function test_set_soul_rejects_aws_key_in_user_context(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('credential-like content');

        $this->profile->setSoul([
            'user_context' => 'AWS access: AKIAIOSFODNN7EXAMPLE',
        ]);
    }

    public function test_set_soul_rejects_password_in_system_prompt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('credential-like content');

        $this->profile->setSoul([
            'system_prompt' => 'DB config: password=s3cret123',
        ]);
    }

    public function test_set_soul_rejects_private_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('credential-like content');

        $this->profile->setSoul([
            'personality' => '-----BEGIN RSA PRIVATE KEY----- some key data',
        ]);
    }

    public function test_set_soul_rejects_exceeding_max_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds maximum length');

        $this->profile->setSoul([
            'personality' => str_repeat('a', 10001),
        ]);
    }

    public function test_round_trip_persistence(): void
    {
        $input = [
            'personality' => 'Careful',
            'system_prompt' => 'Focus on security.',
            'user_context' => 'Financial app.',
        ];

        $this->profile->setSoul($input);
        $this->profile->refresh();

        $output = $this->profile->getSoul();

        $this->assertEquals($input['personality'], $output['personality']);
        $this->assertEquals($input['system_prompt'], $output['system_prompt']);
        $this->assertEquals($input['user_context'], $output['user_context']);
    }
}
