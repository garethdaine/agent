<?php

declare(strict_types=1);

namespace Tests\Feature\Tunnel;

use App\Models\AgentFeatureSetting;
use App\Models\TunnelSetting;
use App\Models\User;
use App\Repositories\TunnelSettingsRepository;
use App\Services\Tunnel\CloudflaredService;
use App\Services\Tunnel\TunnelHealthMonitor;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TunnelGracefulDegradationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function enableTunnelFeature(): void
    {
        AgentFeatureSetting::create([
            'key' => FeatureFlagManager::TUNNEL_ENABLED,
            'is_enabled' => true,
        ]);
    }

    private function configureTunnel(array $settings = [], string $status = 'stopped'): void
    {
        $repo = new TunnelSettingsRepository;
        $repo->update(array_merge([
            'tunnel_name' => 'test-tunnel',
            'tunnel_uuid' => 'test-uuid-1234',
            'hostname' => 'agent.example.com',
            'origin_url' => 'http://localhost:8000',
            'protocol' => 'http2',
        ], $settings), $status);
    }

    // --- Graceful degradation when feature disabled ---

    private function disableTunnelFeature(): void
    {
        config()->set('tunnel.enabled', false);
    }

    public function test_no_tunnel_errors_in_logs_when_feature_disabled(): void
    {
        $this->disableTunnelFeature();

        Log::shouldReceive('error')->never();
        Log::shouldReceive('critical')->never();
        Log::makePartial();

        $this->actingAs($this->user)->get('/dashboard');
        $this->actingAs($this->user)->get('/');

        // No tunnel-related exceptions or errors should have surfaced
        $this->assertTrue(true);
    }

    public function test_no_tunnel_ui_shared_props_when_disabled(): void
    {
        $this->disableTunnelFeature();

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $page = $response->original->getData()['page'];
        $this->assertFalse($page['props']['tunnelEnabled']);
    }

    public function test_all_tunnel_routes_return_404_when_disabled(): void
    {
        $this->disableTunnelFeature();

        $tunnelRouteNames = [
            'settings.tunnel.index',
            'settings.tunnel.wizard',
            'settings.tunnel.update',
            'settings.tunnel.start',
            'settings.tunnel.stop',
            'settings.tunnel.delete',
            'settings.tunnel.install-check',
            'settings.tunnel.install',
            'settings.tunnel.auth-start',
            'settings.tunnel.auth-poll',
            'settings.tunnel.create',
            'settings.tunnel.route-dns',
            'settings.tunnel.access-token',
            'api.tunnel.status',
        ];

        foreach ($tunnelRouteNames as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');

            $response = $this->actingAs($this->user)
                ->json($method, route($routeName));

            $this->assertEquals(
                404,
                $response->getStatusCode(),
                "Route [{$routeName}] should return 404 when tunnel feature is disabled, got {$response->getStatusCode()}"
            );
        }
    }

    // --- Database and config integrity ---

    public function test_tunnel_settings_table_exists_with_default_row(): void
    {
        $repo = new TunnelSettingsRepository;
        $setting = $repo->get();

        $this->assertInstanceOf(TunnelSetting::class, $setting);
        $this->assertEquals('unconfigured', $setting->status);
        $this->assertDatabaseCount('tunnel_settings', 1);
    }

    public function test_horizon_config_loads_with_tunnel_supervisor(): void
    {
        $supervisor = config('horizon.defaults.supervisor-tunnel');

        $this->assertNotNull($supervisor);
        $this->assertEquals(['tunnel'], $supervisor['queue']);
        $this->assertEquals(1, $supervisor['maxProcesses']);
        $this->assertEquals(5, $supervisor['tries']);
        $this->assertEquals(0, $supervisor['timeout']);
    }

    // --- Health monitor graceful behavior ---

    public function test_health_monitor_returns_graceful_defaults_when_cloudflared_missing(): void
    {
        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('isBinaryInstalled')->andReturn(false);
            $mock->shouldReceive('isVersionCompatible')->andReturn([
                'compatible' => false,
                'installed_version' => null,
                'min_version' => CloudflaredService::MIN_VERSION,
                'warning' => 'cloudflared is not installed.',
            ]);
        });

        $monitor = app(TunnelHealthMonitor::class);
        $health = $monitor->getHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('process', $health);
        $this->assertArrayHasKey('metrics', $health);
        $this->assertArrayHasKey('version', $health);
    }

    // --- IP allowlist middleware ---

    public function test_ip_allowlist_middleware_is_noop_when_tunnel_inactive(): void
    {
        $this->configureTunnel(['ip_allowlist' => ['10.0.0.0/8']], 'stopped');

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_ip_allowlist_middleware_is_noop_when_allowlist_empty(): void
    {
        $this->configureTunnel(['ip_allowlist' => []], 'active');

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_ip_allowlist_blocks_when_active_and_configured(): void
    {
        $this->configureTunnel(['ip_allowlist' => ['10.0.0.0/8']], 'active');

        $middleware = app(\App\Http\Middleware\TunnelIpAllowlistMiddleware::class);
        $request = \Illuminate\Http\Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);
        $request->headers->set('CF-Connecting-IP', '192.168.1.1');

        $response = $middleware->handle($request, fn () => new \Illuminate\Http\Response('OK'));

        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('IP_BLOCKED', $data['error']['code']);
    }

    // --- Settings and wizard pages ---

    public function test_settings_page_shows_not_installed_when_binary_missing(): void
    {
        $this->enableTunnelFeature();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('validateCredentials')->andReturn([
                'binary' => false,
                'credentials' => false,
                'functional' => false,
                'version' => null,
                'version_warning' => null,
            ]);
            $mock->shouldReceive('isVersionCompatible')->andReturn([
                'compatible' => false,
                'installed_version' => null,
                'min_version' => '2024.1.0',
                'warning' => 'cloudflared is not installed.',
            ]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('settings.tunnel.index'));

        $response->assertOk();
        $page = $response->original->getData()['page'];
        $this->assertFalse($page['props']['validationChecks']['binary']);
    }

    public function test_wizard_page_accessible_when_tunnel_unconfigured(): void
    {
        $this->enableTunnelFeature();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('validateCredentials')->andReturn([
                'binary' => false,
                'credentials' => false,
                'functional' => false,
                'version' => null,
                'version_warning' => null,
            ]);
            $mock->shouldReceive('isVersionCompatible')->andReturn([
                'compatible' => false,
                'installed_version' => null,
                'min_version' => '2024.1.0',
                'warning' => null,
            ]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('settings.tunnel.wizard'));

        $response->assertOk();
    }

    public function test_start_fails_when_tunnel_unconfigured(): void
    {
        $this->enableTunnelFeature();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.start'));

        $response->assertStatus(422);
        $response->assertJsonPath('errors.tunnel.0', 'Tunnel is not configured. Complete setup first.');
    }
}
