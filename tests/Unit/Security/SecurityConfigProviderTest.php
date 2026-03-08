<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Enums\Runtime\RuntimeMode;
use App\Services\Security\SecurityConfigProvider;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SecurityConfigProviderTest extends TestCase
{
    private SecurityConfigProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new SecurityConfigProvider;
    }

    public function test_immutable_key_content_trust_enabled_always_returns_true(): void
    {
        putenv('SECURITY_CONTENT_TRUST_ENABLED=false');

        $this->assertTrue($this->provider->get('content_trust_enabled'));

        putenv('SECURITY_CONTENT_TRUST_ENABLED');
    }

    public function test_immutable_key_injection_detection_enabled_always_returns_true(): void
    {
        putenv('SECURITY_INJECTION_DETECTION_ENABLED=false');

        $this->assertTrue($this->provider->get('injection_detection_enabled'));

        putenv('SECURITY_INJECTION_DETECTION_ENABLED');
    }

    public function test_immutable_key_exfiltration_detection_always_returns_true(): void
    {
        putenv('SECURITY_EXFILTRATION_DETECTION=false');

        $this->assertTrue($this->provider->get('exfiltration_detection'));

        putenv('SECURITY_EXFILTRATION_DETECTION');
    }

    public function test_threshold_floor_clamps_high_values(): void
    {
        putenv('SECURITY_INJECTION_THRESHOLD_SAFE=0.95');

        $result = $this->provider->get('injection_threshold_safe');

        $this->assertSame(0.9, $result);

        putenv('SECURITY_INJECTION_THRESHOLD_SAFE');
    }

    public function test_threshold_normal_passes_through(): void
    {
        putenv('SECURITY_INJECTION_THRESHOLD_SAFE=0.3');

        $result = $this->provider->get('injection_threshold_safe');

        $this->assertSame(0.3, $result);

        putenv('SECURITY_INJECTION_THRESHOLD_SAFE');
    }

    public function test_token_ceiling_clamps_high_values(): void
    {
        putenv('SECURITY_TOOL_RESULT_MAX_TOKENS=50000');

        $result = $this->provider->get('tool_result_max_tokens');

        $this->assertSame(32000, $result);

        putenv('SECURITY_TOOL_RESULT_MAX_TOKENS');
    }

    public function test_token_normal_passes_through(): void
    {
        putenv('SECURITY_TOOL_RESULT_MAX_TOKENS=8000');

        $result = $this->provider->get('tool_result_max_tokens');

        $this->assertSame(8000, $result);

        putenv('SECURITY_TOOL_RESULT_MAX_TOKENS');
    }

    public function test_default_values_returned_when_no_env_set(): void
    {
        $this->assertSame(0.3, $this->provider->get('injection_threshold_safe'));
        $this->assertSame(0.5, $this->provider->get('injection_threshold_standard'));
        $this->assertSame(0.7, $this->provider->get('injection_threshold_full'));
        $this->assertSame(8000, $this->provider->get('tool_result_max_tokens'));
        $this->assertTrue($this->provider->get('strip_html'));
        $this->assertFalse($this->provider->get('default_deny_external'));
        $this->assertSame(20, $this->provider->get('messenger_rate_limit'));
        $this->assertSame('ignore', $this->provider->get('messenger_group_policy'));
        $this->assertTrue($this->provider->get('prompt_isolation'));
        $this->assertSame(4000, $this->provider->get('context_budget_tokens'));
        $this->assertFalse($this->provider->get('content_wrapping_markers'));
        $this->assertSame(30, $this->provider->get('file_provenance_retention_days'));
        $this->assertSame(1000, $this->provider->get('security_purge_batch_size'));
        $this->assertTrue($this->provider->get('messenger_high_impact_confirmation'));
    }

    public function test_get_threshold_returns_safe_threshold(): void
    {
        $result = $this->provider->getThreshold(RuntimeMode::Safe);
        $this->assertSame(0.3, $result);
    }

    public function test_get_threshold_returns_standard_threshold(): void
    {
        $result = $this->provider->getThreshold(RuntimeMode::Standard);
        $this->assertSame(0.5, $result);
    }

    public function test_get_threshold_returns_full_threshold(): void
    {
        $result = $this->provider->getThreshold(RuntimeMode::Full);
        $this->assertSame(0.7, $result);
    }

    public function test_is_immutable_returns_true_for_immutable_keys(): void
    {
        $this->assertTrue($this->provider->isImmutable('content_trust_enabled'));
        $this->assertTrue($this->provider->isImmutable('injection_detection_enabled'));
        $this->assertTrue($this->provider->isImmutable('exfiltration_detection'));
    }

    public function test_is_immutable_returns_false_for_non_immutable_keys(): void
    {
        $this->assertFalse($this->provider->isImmutable('injection_threshold_safe'));
        $this->assertFalse($this->provider->isImmutable('tool_result_max_tokens'));
        $this->assertFalse($this->provider->isImmutable('strip_html'));
    }

    public function test_flush_cache_clears_redis_entries_for_account(): void
    {
        $redis = Redis::shouldReceive('connection')->with('cache')->andReturnSelf();
        $redis->shouldReceive('del')->times(18);

        $this->provider->flushCache(42);
    }

    public function test_flush_cache_clears_all_redis_entries(): void
    {
        $redis = Redis::shouldReceive('connection')->with('cache')->andReturnSelf();
        $redis->shouldReceive('keys')->with('security:config:*')->andReturn(['key1', 'key2']);
        $redis->shouldReceive('del')->with('key1', 'key2')->once();

        $this->provider->flushCache();
    }
}
