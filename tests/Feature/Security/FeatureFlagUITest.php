<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagUITest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_feature_flags_index_contains_security_content_trust_label(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/agent/api/v1/features/settings');

        $response->assertOk();

        $flags = collect($response->json('data'));
        $flag = $flags->firstWhere('key', FeatureFlagManager::SECURITY_CONTENT_TRUST);

        $this->assertNotNull($flag);
        $this->assertSame('Content Trust Classification', $flag['label']);
        $this->assertTrue($flag['is_enabled']);
    }

    public function test_feature_flags_index_contains_injection_detection_label(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/agent/api/v1/features/settings');

        $response->assertOk();

        $flags = collect($response->json('data'));
        $flag = $flags->firstWhere('key', FeatureFlagManager::SECURITY_INJECTION_DETECTION);

        $this->assertNotNull($flag);
        $this->assertSame('Injection Detection', $flag['label']);
        $this->assertTrue($flag['is_enabled']);
    }

    public function test_feature_flags_index_contains_exfiltration_detection_label(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/agent/api/v1/features/settings');

        $response->assertOk();

        $flags = collect($response->json('data'));
        $flag = $flags->firstWhere('key', FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION);

        $this->assertNotNull($flag);
        $this->assertSame('Exfiltration Detection', $flag['label']);
        $this->assertTrue($flag['is_enabled']);
    }

    public function test_all_three_security_flags_shown_as_enabled(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/agent/api/v1/features/settings');

        $response->assertOk();

        $flags = collect($response->json('data'));
        $securityKeys = FeatureFlagManager::getSecurityFlags();

        foreach ($securityKeys as $key) {
            $flag = $flags->firstWhere('key', $key);
            $this->assertNotNull($flag, "Security flag {$key} should appear in response");
            $this->assertTrue($flag['is_enabled'], "Security flag {$key} should be enabled");
        }
    }

    public function test_update_with_security_flags_false_still_returns_true(): void
    {
        $this->actingAs($this->user);

        $response = $this->putJson('/agent/api/v1/features/settings', [
            'flags' => [
                FeatureFlagManager::SECURITY_CONTENT_TRUST => false,
                FeatureFlagManager::SECURITY_INJECTION_DETECTION => false,
                FeatureFlagManager::SECURITY_EXFILTRATION_DETECTION => false,
            ],
        ]);

        $response->assertOk();

        $flags = collect($response->json('data'));

        foreach (FeatureFlagManager::getSecurityFlags() as $key) {
            $flag = $flags->firstWhere('key', $key);
            $this->assertNotNull($flag, "Security flag {$key} should appear in response after update");
            $this->assertTrue($flag['is_enabled'], "Security flag {$key} should remain true after attempting to disable");
        }
    }

    public function test_security_flag_descriptions_contain_immutable_notice(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/agent/api/v1/features/settings');

        $response->assertOk();

        $flags = collect($response->json('data'));

        foreach (FeatureFlagManager::getSecurityFlags() as $key) {
            $flag = $flags->firstWhere('key', $key);
            $this->assertNotNull($flag);
            $this->assertStringContainsString(
                'Immutable: cannot be disabled',
                $flag['description'],
                "Security flag {$key} description should contain immutable notice"
            );
        }
    }
}
