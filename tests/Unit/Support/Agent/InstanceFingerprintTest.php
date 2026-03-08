<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Agent;

use App\Models\AgentSystemState;
use App\Support\Agent\InstanceFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceFingerprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_a_non_empty_string(): void
    {
        $fingerprint = app(InstanceFingerprint::class);

        $result = $fingerprint->generate();

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function test_produces_a_64_character_hex_hash(): void
    {
        $fingerprint = app(InstanceFingerprint::class);

        $result = $fingerprint->generate();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    public function test_returns_same_fingerprint_on_consecutive_calls(): void
    {
        $fingerprint = app(InstanceFingerprint::class);

        $first = $fingerprint->generate();
        $second = $fingerprint->generate();

        $this->assertSame($first, $second);
    }

    public function test_persists_install_salt_in_system_state(): void
    {
        $fingerprint = app(InstanceFingerprint::class);

        $fingerprint->generate();

        $this->assertDatabaseHas('agent_system_state', [
            'key' => 'instance_install_salt',
        ]);
    }

    public function test_reuses_persisted_salt(): void
    {
        AgentSystemState::updateOrCreate(
            ['key' => 'instance_install_salt'],
            ['value' => 'fixed-salt-for-test']
        );

        $fingerprint = app(InstanceFingerprint::class);
        $first = $fingerprint->generate();
        $second = $fingerprint->generate();

        $this->assertSame($first, $second);

        $stored = AgentSystemState::where('key', 'instance_install_salt')->first();
        $this->assertSame('fixed-salt-for-test', $stored->value);
    }
}
