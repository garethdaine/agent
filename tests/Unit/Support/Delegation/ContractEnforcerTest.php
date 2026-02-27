<?php

namespace Tests\Unit\Support\Delegation;

use App\Models\DelegateeProfile;
use App\Models\User;
use App\Support\Delegation\ContractEnforcer;
use App\Support\Delegation\EnforcementResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractEnforcerTest extends TestCase
{
    use RefreshDatabase;

    private ContractEnforcer $enforcer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enforcer = new ContractEnforcer;
    }

    public function test_narrows_paths_to_policy_intersection(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'config_json' => [
                'allowed_paths' => ['/allowed/path1', '/allowed/path2'],
            ],
        ]);

        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'allowed_paths' => ['/allowed/path1', '/outside/path'],
            ],
        ];

        $result = $this->enforcer->enforce($contract, $profile);

        $this->assertInstanceOf(EnforcementResult::class, $result);
        // Should narrow to only paths in both contract and profile
        $narrowedPaths = $result->narrowedConfig()['authority_scope']['allowed_paths'] ?? [];
        $this->assertContains('/allowed/path1', $narrowedPaths);
        $this->assertNotContains('/outside/path', $narrowedPaths);
    }

    public function test_narrows_env_to_policy_intersection(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'config_json' => [
                'env_whitelist' => ['ALLOWED_VAR', 'ANOTHER_VAR'],
            ],
        ]);

        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'env_whitelist' => ['ALLOWED_VAR', 'FORBIDDEN_VAR'],
            ],
        ];

        $result = $this->enforcer->enforce($contract, $profile);

        $narrowedEnv = $result->narrowedConfig()['authority_scope']['env_whitelist'] ?? [];
        $this->assertContains('ALLOWED_VAR', $narrowedEnv);
        $this->assertNotContains('FORBIDDEN_VAR', $narrowedEnv);
    }

    public function test_caps_max_runtime_to_profile_limit(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'config_json' => [
                'max_runtime_seconds' => 3600, // 1 hour profile limit
            ],
        ]);

        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 7200, // Request 2 hours
            ],
        ];

        $result = $this->enforcer->enforce($contract, $profile);

        // Should cap to profile limit
        $narrowedRuntime = $result->narrowedConfig()['authority_scope']['max_runtime_seconds'] ?? 0;
        $this->assertEquals(3600, $narrowedRuntime);
    }

    public function test_records_warning_when_narrowed(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'config_json' => [
                'max_runtime_seconds' => 3600,
            ],
        ]);

        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 7200, // Exceeds limit
            ],
        ];

        $result = $this->enforcer->enforce($contract, $profile);

        $this->assertNotEmpty($result->warnings());
        $this->assertStringContainsString('max_runtime_seconds', implode(' ', $result->warnings()));
    }

    public function test_returns_unchanged_when_within_bounds(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'config_json' => [
                'max_runtime_seconds' => 7200,
                'allowed_paths' => ['/path/one', '/path/two'],
            ],
        ]);

        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 3600, // Within limit
                'allowed_paths' => ['/path/one'], // Subset of allowed
            ],
        ];

        $result = $this->enforcer->enforce($contract, $profile);

        $this->assertFalse($result->wasNarrowed());
        $this->assertEmpty($result->warnings());
    }

    public function test_was_narrowed_flag_accurate(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'config_json' => [
                'max_runtime_seconds' => 3600,
            ],
        ]);

        // Test with narrowing needed
        $contractNeedsNarrowing = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 7200,
            ],
        ];

        $result1 = $this->enforcer->enforce($contractNeedsNarrowing, $profile);
        $this->assertTrue($result1->wasNarrowed());

        // Test without narrowing needed
        $contractWithinBounds = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 1800,
            ],
        ];

        $result2 = $this->enforcer->enforce($contractWithinBounds, $profile);
        $this->assertFalse($result2->wasNarrowed());
    }

    public function test_handles_missing_authority_scope(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create();

        $contract = [
            'prompt' => 'Do something',
        ];

        $result = $this->enforcer->enforce($contract, $profile);

        $this->assertInstanceOf(EnforcementResult::class, $result);
        $this->assertFalse($result->wasNarrowed());
    }

    public function test_handles_missing_profile_config(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'config_json' => null,
        ]);

        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 7200,
            ],
        ];

        // Should still enforce system limits even without profile config
        $result = $this->enforcer->enforce($contract, $profile);

        $this->assertInstanceOf(EnforcementResult::class, $result);
    }

    public function test_caps_to_system_ceiling_when_profile_has_no_limit(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create([
            'config_json' => [], // No limits in profile
        ]);

        $contract = [
            'prompt' => 'Do something',
            'authority_scope' => [
                'max_runtime_seconds' => 100000, // Exceeds 24-hour system ceiling
            ],
        ];

        $result = $this->enforcer->enforce($contract, $profile);

        // Should cap to system ceiling of 86400 (24 hours)
        $narrowedRuntime = $result->narrowedConfig()['authority_scope']['max_runtime_seconds'] ?? 0;
        $this->assertLessThanOrEqual(86400, $narrowedRuntime);
        $this->assertTrue($result->wasNarrowed());
    }

    public function test_preserves_non_scope_contract_fields(): void
    {
        $user = User::factory()->create();
        $profile = DelegateeProfile::factory()->for($user)->create();

        $contract = [
            'prompt' => 'Do something specific',
            'criticality' => 'high',
            'authority_scope' => [
                'max_runtime_seconds' => 3600,
            ],
        ];

        $result = $this->enforcer->enforce($contract, $profile);

        $narrowed = $result->narrowedConfig();
        $this->assertEquals('Do something specific', $narrowed['prompt']);
        $this->assertEquals('high', $narrowed['criticality']);
    }
}
