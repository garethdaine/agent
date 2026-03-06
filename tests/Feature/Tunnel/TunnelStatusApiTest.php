<?php

declare(strict_types=1);

namespace Tests\Feature\Tunnel;

use App\Models\AgentFeatureSetting;
use App\Models\User;
use App\Services\Tunnel\TunnelHealthMonitor;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TunnelStatusApiTest extends TestCase
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

    public function test_status_returns_health_json_when_enabled(): void
    {
        $this->enableTunnelFeature();

        $this->mock(TunnelHealthMonitor::class, function ($mock) {
            $mock->shouldReceive('getHealth')->once()->andReturn([
                'status' => 'active',
                'hostname' => 'agent.example.com',
                'process' => ['alive' => true, 'pid' => 12345],
                'metrics' => ['connections' => 5, 'error_count' => 0, 'uptime_seconds' => 3600, 'metrics_available' => true],
                'version' => ['compatible' => true, 'installed_version' => '2024.6.1', 'min_version' => '2024.1.0', 'warning' => null],
            ]);
        });

        $response = $this->actingAs($this->user)
            ->getJson(route('api.tunnel.status'));

        $response->assertOk();
        $response->assertJsonPath('status', 'active');
        $response->assertJsonPath('hostname', 'agent.example.com');
    }

    public function test_status_returns_404_when_disabled(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('api.tunnel.status'));

        $response->assertStatus(404);
    }

    public function test_status_response_schema_matches_expected(): void
    {
        $this->enableTunnelFeature();

        $this->mock(TunnelHealthMonitor::class, function ($mock) {
            $mock->shouldReceive('getHealth')->once()->andReturn([
                'status' => 'stopped',
                'hostname' => '',
                'process' => ['alive' => false, 'pid' => null],
                'metrics' => ['connections' => 0, 'error_count' => 0, 'uptime_seconds' => 0, 'metrics_available' => false],
                'version' => ['compatible' => true, 'installed_version' => '2024.6.1', 'min_version' => '2024.1.0', 'warning' => null],
            ]);
        });

        $response = $this->actingAs($this->user)
            ->getJson(route('api.tunnel.status'));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'hostname',
            'process' => ['alive', 'pid'],
            'metrics' => ['connections', 'error_count', 'uptime_seconds', 'metrics_available'],
            'version' => ['compatible', 'installed_version', 'min_version', 'warning'],
        ]);
    }
}
