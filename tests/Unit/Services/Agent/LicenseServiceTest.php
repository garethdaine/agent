<?php

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\LicenseService;
use App\Support\Agent\InstanceFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private LicenseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agent.license.key' => 'test-license-key-123',
            'agent.license.validation_url' => 'https://agent-ops.com/api/license/validate',
            'agent.license.cache_ttl_seconds' => 3600,
            'agent.license.bypass_domains' => ['.test', '.local', 'localhost'],
            'agent.license.bypass_subdomains' => ['dev.', 'staging.'],
            'app.url' => 'https://production.example.com',
        ]);

        Cache::forget('agent_license_status');

        $this->service = new LicenseService(app(InstanceFingerprint::class));
    }

    public function test_bypass_returns_valid_for_local_domain(): void
    {
        config(['app.url' => 'https://myapp.test']);

        $result = $this->service->validate();

        $this->assertTrue($result->valid);
        $this->assertSame('development', $result->plan);
    }

    public function test_bypass_returns_valid_for_localhost(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $result = $this->service->validate();

        $this->assertTrue($result->valid);
    }

    public function test_bypass_returns_valid_for_bypass_subdomain(): void
    {
        config(['app.url' => 'https://dev.production.com']);

        $result = $this->service->validate();

        $this->assertTrue($result->valid);
    }

    public function test_should_bypass_returns_true_for_test_domains(): void
    {
        config(['app.url' => 'https://agent.test']);
        $this->assertTrue($this->service->shouldBypass());
    }

    public function test_should_bypass_returns_false_for_production_domains(): void
    {
        config(['app.url' => 'https://app.production.com']);
        $this->assertFalse($this->service->shouldBypass());
    }

    public function test_validate_calls_remote_api_with_correct_payload(): void
    {
        Http::fake([
            'agent-ops.com/api/license/validate' => Http::response([
                'valid' => true,
                'license' => ['plan' => 'pro', 'expires_at' => '2027-01-01', 'features' => []],
            ]),
        ]);

        $result = $this->service->validate();

        $this->assertTrue($result->valid);
        $this->assertSame('pro', $result->plan);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://agent-ops.com/api/license/validate'
                && $request['license_key'] === 'test-license-key-123'
                && isset($request['fingerprint'])
                && isset($request['domain'])
                && $request['app_version'] === '1.0.0';
        });
    }

    public function test_validate_returns_invalid_on_api_rejection(): void
    {
        Http::fake([
            'agent-ops.com/api/license/validate' => Http::response([
                'valid' => false,
                'error' => 'License expired',
            ]),
        ]);

        $result = $this->service->validate();

        $this->assertFalse($result->valid);
        $this->assertSame('License expired', $result->error);
    }

    public function test_validate_caches_result(): void
    {
        Http::fake([
            'agent-ops.com/api/license/validate' => Http::response([
                'valid' => true,
                'license' => ['plan' => 'standard', 'expires_at' => null, 'features' => []],
            ]),
        ]);

        $this->service->validate();
        $this->service->validate();

        Http::assertSentCount(1);
    }

    public function test_is_valid_returns_cached_status(): void
    {
        Http::fake([
            'agent-ops.com/api/license/validate' => Http::response([
                'valid' => true,
                'license' => ['plan' => 'standard', 'expires_at' => null, 'features' => []],
            ]),
        ]);

        $this->service->validate();

        $this->assertTrue($this->service->isValid());
    }

    public function test_is_valid_returns_false_when_no_cached_result(): void
    {
        $this->assertFalse($this->service->isValid());
    }

    public function test_clear_cache_removes_cached_status(): void
    {
        Http::fake([
            'agent-ops.com/api/license/validate' => Http::response([
                'valid' => true,
                'license' => ['plan' => 'standard', 'expires_at' => null, 'features' => []],
            ]),
        ]);

        $this->service->validate();
        $this->assertTrue($this->service->isValid());

        $this->service->clearCache();
        $this->assertFalse($this->service->isValid());
    }

    public function test_graceful_degradation_on_http_failure_with_cached_result(): void
    {
        Cache::put('agent_license_status', ['valid' => true, 'plan' => 'pro', 'expires_at' => null, 'features' => [], 'error' => null], 3600);

        Http::fake([
            'agent-ops.com/api/license/validate' => Http::response(null, 500),
        ]);

        $this->service->clearCache();

        Cache::put('agent_license_status', ['valid' => true, 'plan' => 'pro', 'expires_at' => null, 'features' => [], 'error' => null], 3600);

        $result = $this->service->validate();

        $this->assertTrue($result->valid);
    }

    public function test_graceful_degradation_on_http_failure_without_cache(): void
    {
        Http::fake([
            'agent-ops.com/api/license/validate' => Http::response(null, 500),
        ]);

        $result = $this->service->validate();

        $this->assertFalse($result->valid);
    }

    public function test_validate_returns_invalid_when_no_license_key_configured(): void
    {
        config(['agent.license.key' => '']);

        $result = $this->service->validate();

        $this->assertFalse($result->valid);
    }

    public function test_instance_fingerprint_returns_string(): void
    {
        $fingerprint = $this->service->instanceFingerprint();

        $this->assertNotEmpty($fingerprint);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fingerprint);
    }
}
