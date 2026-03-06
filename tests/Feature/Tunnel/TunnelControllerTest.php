<?php

declare(strict_types=1);

namespace Tests\Feature\Tunnel;

use App\Jobs\Tunnel\TunnelRunJob;
use App\Models\AgentFeatureSetting;
use App\Models\User;
use App\Repositories\TunnelSettingsRepository;
use App\Services\Tunnel\CloudflaredService;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TunnelControllerTest extends TestCase
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

    // --- Index ---

    public function test_index_returns_200_when_feature_enabled(): void
    {
        $this->enableTunnelFeature();

        $response = $this->actingAs($this->user)
            ->get(route('settings.tunnel.index'));

        $response->assertOk();
    }

    public function test_index_returns_404_when_feature_disabled(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('settings.tunnel.index'));

        $response->assertStatus(404);
    }

    // --- Wizard ---

    public function test_wizard_returns_200_when_feature_enabled(): void
    {
        $this->enableTunnelFeature();

        $response = $this->actingAs($this->user)
            ->get(route('settings.tunnel.wizard'));

        $response->assertOk();
    }

    // --- Update ---

    public function test_update_validates_and_persists_settings(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.update'), [
                'hostname' => 'new.example.com',
                'protocol' => 'quic',
                'cloudflare_access_enabled' => true,
            ]);

        $response->assertOk();

        $repo = new TunnelSettingsRepository;
        $settings = $repo->getSettings();
        $this->assertEquals('new.example.com', $settings['hostname']);
        $this->assertEquals(config('app.url'), $settings['origin_url']);
        $this->assertEquals('quic', $settings['protocol']);
    }

    public function test_update_rejects_invalid_hostname(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.update'), [
                'hostname' => 'not a valid hostname!!!',
                'protocol' => 'http2',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['hostname']);
    }

    public function test_update_validates_cidr_format_for_ip_allowlist(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.update'), [
                'protocol' => 'http2',
                'ip_allowlist' => ['not-a-cidr', '999.999.999.999/32'],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['ip_allowlist.0']);
    }

    // --- Start ---

    public function test_start_dispatches_tunnel_run_job(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel();
        Bus::fake();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.start'));

        $response->assertOk();
        Bus::assertDispatched(TunnelRunJob::class);
    }

    public function test_start_fails_when_unconfigured(): void
    {
        $this->enableTunnelFeature();
        // Create default unconfigured row
        (new TunnelSettingsRepository)->get();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.start'));

        $response->assertStatus(422);
    }

    // --- Stop ---

    public function test_stop_updates_status_to_stopped(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel([], 'active');
        Process::fake();

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.stop'));

        $response->assertOk();

        $repo = new TunnelSettingsRepository;
        $this->assertEquals('stopped', $repo->getStatus());
    }

    // --- Delete ---

    public function test_delete_clears_settings_and_status(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('deleteTunnel')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.delete'));

        $response->assertOk();

        $repo = new TunnelSettingsRepository;
        $this->assertEquals('unconfigured', $repo->getStatus());
    }

    // --- Install Check ---

    public function test_install_check_returns_validation_results(): void
    {
        $this->enableTunnelFeature();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('validateCredentials')->once()->andReturn([
                'binary' => true,
                'credentials' => true,
                'functional' => true,
                'version' => '2024.6.1',
                'version_warning' => null,
            ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.install-check'));

        $response->assertOk();
        $response->assertJsonStructure(['binary', 'credentials', 'functional', 'version', 'platform', 'install_command']);
    }

    public function test_install_returns_403_when_allow_install_disabled(): void
    {
        $this->enableTunnelFeature();
        config(['tunnel.allow_install' => false]);

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.install'));

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    public function test_install_returns_success_when_already_installed(): void
    {
        $this->enableTunnelFeature();
        config(['tunnel.allow_install' => true]);

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('validateCredentials')->once()->andReturn([
                'binary' => true,
                'credentials' => false,
                'functional' => false,
            ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.install'));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'cloudflared is already installed.');
    }

    // --- Auth Start ---

    public function test_auth_start_returns_auth_url(): void
    {
        $this->enableTunnelFeature();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('startLogin')->once()->andReturn([
                'success' => true,
                'auth_url' => 'https://dash.cloudflare.com/argotunnel?callback=test',
                'error' => null,
            ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.auth-start'));

        $response->assertOk();
        $response->assertJsonPath('auth_url', 'https://dash.cloudflare.com/argotunnel?callback=test');
    }

    // --- Auth Poll ---

    public function test_auth_poll_returns_authentication_status(): void
    {
        $this->enableTunnelFeature();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('pollLoginComplete')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.auth-poll'));

        $response->assertOk();
        $response->assertJsonPath('authenticated', true);
    }

    // --- Create Tunnel ---

    public function test_create_tunnel_stores_uuid_in_settings(): void
    {
        $this->enableTunnelFeature();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('createTunnel')
                ->with('my-tunnel')
                ->once()
                ->andReturn([
                    'success' => true,
                    'id' => 'uuid-from-cloudflare',
                    'name' => 'my-tunnel',
                    'error' => null,
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.create'), [
                'tunnel_name' => 'my-tunnel',
                'hostname' => 'agent.example.com',
            ]);

        $response->assertOk();
        $response->assertJsonPath('tunnel_uuid', 'uuid-from-cloudflare');

        $repo = new TunnelSettingsRepository;
        $settings = $repo->getSettings();
        $this->assertEquals('uuid-from-cloudflare', $settings['tunnel_uuid']);
        $this->assertEquals('agent.example.com', $settings['hostname']);
    }

    // --- Use Existing Tunnel ---

    public function test_use_existing_tunnel_stores_uuid_when_found(): void
    {
        $this->enableTunnelFeature();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('listTunnels')
                ->once()
                ->andReturn([
                    ['id' => 'existing-uuid-5678', 'name' => 'my-tunnel'],
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.use-existing'), [
                'tunnel_name' => 'my-tunnel',
                'hostname' => 'agent.example.com',
            ]);

        $response->assertOk();
        $response->assertJsonPath('tunnel_uuid', 'existing-uuid-5678');

        $repo = new TunnelSettingsRepository;
        $settings = $repo->getSettings();
        $this->assertEquals('existing-uuid-5678', $settings['tunnel_uuid']);
        $this->assertEquals('my-tunnel', $settings['tunnel_name']);
        $this->assertEquals('agent.example.com', $settings['hostname']);
    }

    public function test_use_existing_tunnel_returns_422_when_not_found(): void
    {
        $this->enableTunnelFeature();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('listTunnels')
                ->once()
                ->andReturn([
                    ['id' => 'other-uuid', 'name' => 'other-tunnel'],
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.use-existing'), [
                'tunnel_name' => 'my-tunnel',
                'hostname' => 'agent.example.com',
            ]);

        $response->assertStatus(422);
    }

    // --- Route DNS ---

    public function test_route_dns_returns_success_or_manual_instructions(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('routeDns')
                ->once()
                ->andReturn([
                    'success' => true,
                    'cname_target' => 'test-uuid-1234.cfargotunnel.com',
                    'error' => null,
                    'manual_instructions' => null,
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.route-dns'));

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    // --- Generate Access Token ---

    public function test_generate_access_token_stores_and_returns_credentials(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel();

        $this->mock(CloudflaredService::class, function ($mock) {
            $mock->shouldReceive('generateAccessServiceToken')
                ->once()
                ->andReturn([
                    'success' => true,
                    'client_id' => 'cf-client-id',
                    'client_secret' => 'cf-client-secret',
                    'error' => null,
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.access-token'));

        $response->assertOk();
        $response->assertJsonPath('client_id', 'cf-client-id');
        $response->assertJsonPath('client_secret', 'cf-client-secret');

        $repo = new TunnelSettingsRepository;
        $settings = $repo->getSettings();
        $this->assertEquals('cf-client-id', $settings['access_client_id']);
    }

    public function test_generate_access_token_hides_secret_on_subsequent_calls(): void
    {
        $this->enableTunnelFeature();
        $this->configureTunnel([
            'access_client_id' => 'existing-client-id',
            'access_client_secret' => 'existing-secret',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('settings.tunnel.access-token'));

        $response->assertOk();
        $response->assertJsonPath('client_id', 'existing-client-id');
        $response->assertJsonMissing(['client_secret' => 'existing-secret']);
        $response->assertJsonPath('client_secret', null);
    }

    // --- Auth requirement ---

    public function test_all_routes_require_authentication(): void
    {
        $this->enableTunnelFeature();

        $routes = [
            ['GET', route('settings.tunnel.index')],
            ['GET', route('settings.tunnel.wizard')],
            ['POST', route('settings.tunnel.update')],
            ['POST', route('settings.tunnel.start')],
            ['POST', route('settings.tunnel.stop')],
            ['POST', route('settings.tunnel.delete')],
            ['POST', route('settings.tunnel.install-check')],
            ['POST', route('settings.tunnel.install')],
            ['POST', route('settings.tunnel.auth-start')],
            ['POST', route('settings.tunnel.auth-poll')],
            ['POST', route('settings.tunnel.create')],
            ['POST', route('settings.tunnel.route-dns')],
            ['POST', route('settings.tunnel.access-token')],
            ['GET', route('api.tunnel.status')],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $method === 'GET'
                ? $this->get($url)
                : $this->postJson($url);

            $this->assertTrue(
                in_array($response->status(), [302, 401]),
                "Route {$method} {$url} should require authentication but returned {$response->status()}"
            );
        }
    }
}
