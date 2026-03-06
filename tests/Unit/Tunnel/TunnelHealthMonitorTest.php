<?php

declare(strict_types=1);

namespace Tests\Unit\Tunnel;

use App\Repositories\TunnelSettingsRepository;
use App\Services\Tunnel\CloudflaredService;
use App\Services\Tunnel\TunnelHealthMonitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TunnelHealthMonitorTest extends TestCase
{
    private TunnelHealthMonitor $monitor;

    private TunnelSettingsRepository $repository;

    private CloudflaredService $cloudflaredService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->mock(TunnelSettingsRepository::class);
        $this->cloudflaredService = $this->mock(CloudflaredService::class);

        $this->monitor = new TunnelHealthMonitor(
            $this->repository,
            $this->cloudflaredService,
        );
    }

    public function test_check_process_status_alive(): void
    {
        Process::fake([
            "pgrep -f 'cloudflared tunnel run'" => Process::result(output: '12345', exitCode: 0),
        ]);

        $result = $this->monitor->checkProcessStatus();

        $this->assertTrue($result['alive']);
        $this->assertSame(12345, $result['pid']);
    }

    public function test_check_process_status_dead(): void
    {
        Process::fake([
            "pgrep -f 'cloudflared tunnel run'" => Process::result(output: '', exitCode: 1),
        ]);

        $result = $this->monitor->checkProcessStatus();

        $this->assertFalse($result['alive']);
        $this->assertNull($result['pid']);
    }

    public function test_fetch_metrics_parses_prometheus_text(): void
    {
        $prometheusText = <<<'PROM'
# HELP cloudflared_tunnel_connections Number of active tunnel connections
# TYPE cloudflared_tunnel_connections gauge
cloudflared_tunnel_connections 4
# HELP cloudflared_tunnel_request_errors_total Total number of request errors
# TYPE cloudflared_tunnel_request_errors_total counter
cloudflared_tunnel_request_errors_total 12
# HELP process_start_time_seconds Start time of the process since unix epoch in seconds
# TYPE process_start_time_seconds gauge
process_start_time_seconds 1709712000.0
PROM;

        Http::fake([
            'http://localhost:20241/metrics' => Http::response($prometheusText, 200),
        ]);

        $result = $this->monitor->fetchMetrics();

        $this->assertTrue($result['metrics_available']);
        $this->assertSame(4, $result['connections']);
        $this->assertSame(12, $result['error_count']);
        $this->assertGreaterThan(0, $result['uptime_seconds']);
    }

    public function test_fetch_metrics_handles_connection_refused(): void
    {
        Http::fake([
            'http://localhost:20241/metrics' => Http::response('', 500),
        ]);

        // Override to simulate connection failure via exception
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $result = $this->monitor->fetchMetrics();

        $this->assertFalse($result['metrics_available']);
        $this->assertSame(0, $result['connections']);
        $this->assertSame(0, $result['error_count']);
        $this->assertSame(0, $result['uptime_seconds']);
    }

    public function test_fetch_metrics_handles_malformed_response(): void
    {
        Http::fake([
            'http://localhost:20241/metrics' => Http::response('not prometheus format at all', 200),
        ]);

        $result = $this->monitor->fetchMetrics();

        $this->assertTrue($result['metrics_available']);
        $this->assertSame(0, $result['connections']);
        $this->assertSame(0, $result['error_count']);
        $this->assertSame(0, $result['uptime_seconds']);
    }

    public function test_get_health_aggregates_all_sources(): void
    {
        Process::fake([
            "pgrep -f 'cloudflared tunnel run'" => Process::result(output: '12345', exitCode: 0),
        ]);

        Http::fake([
            'http://localhost:20241/metrics' => Http::response(
                "cloudflared_tunnel_connections 2\ncloudflared_tunnel_request_errors_total 0\nprocess_start_time_seconds ".time().".0\n",
                200,
            ),
        ]);

        $this->repository->shouldReceive('getStatus')->once()->andReturn('active');
        $this->repository->shouldReceive('getSettings')->once()->andReturn([
            'hostname' => 'agent.example.com',
        ]);

        $this->cloudflaredService->shouldReceive('isVersionCompatible')->once()->andReturn([
            'compatible' => true,
            'installed_version' => '2024.6.1',
            'min_version' => '2024.1.0',
            'warning' => null,
        ]);

        $health = $this->monitor->getHealth();

        $this->assertSame('active', $health['status']);
        $this->assertTrue($health['process']['alive']);
        $this->assertSame(12345, $health['process']['pid']);
        $this->assertTrue($health['metrics']['metrics_available']);
        $this->assertSame(2, $health['metrics']['connections']);
        $this->assertSame('agent.example.com', $health['hostname']);
        $this->assertTrue($health['version']['compatible']);
    }

    public function test_get_health_when_tunnel_unconfigured(): void
    {
        Process::fake([
            "pgrep -f 'cloudflared tunnel run'" => Process::result(output: '', exitCode: 1),
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->repository->shouldReceive('getStatus')->once()->andReturn('unconfigured');
        $this->repository->shouldReceive('getSettings')->once()->andReturn([
            'hostname' => '',
        ]);

        $this->cloudflaredService->shouldReceive('isVersionCompatible')->once()->andReturn([
            'compatible' => false,
            'installed_version' => null,
            'min_version' => '2024.1.0',
            'warning' => 'Could not determine cloudflared version.',
        ]);

        $health = $this->monitor->getHealth();

        $this->assertSame('unconfigured', $health['status']);
        $this->assertFalse($health['process']['alive']);
        $this->assertFalse($health['metrics']['metrics_available']);
        $this->assertSame('', $health['hostname']);
    }

    public function test_get_health_when_process_dead(): void
    {
        Process::fake([
            "pgrep -f 'cloudflared tunnel run'" => Process::result(output: '', exitCode: 1),
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->repository->shouldReceive('getStatus')->once()->andReturn('error');
        $this->repository->shouldReceive('getSettings')->once()->andReturn([
            'hostname' => 'agent.example.com',
        ]);

        $this->cloudflaredService->shouldReceive('isVersionCompatible')->once()->andReturn([
            'compatible' => true,
            'installed_version' => '2024.6.1',
            'min_version' => '2024.1.0',
            'warning' => null,
        ]);

        $health = $this->monitor->getHealth();

        $this->assertSame('error', $health['status']);
        $this->assertFalse($health['process']['alive']);
        $this->assertFalse($health['metrics']['metrics_available']);
        $this->assertSame('agent.example.com', $health['hostname']);
    }

    public function test_uptime_calculation_from_process_start_time(): void
    {
        $startTime = time() - 3600; // 1 hour ago

        Http::fake([
            'http://localhost:20241/metrics' => Http::response(
                "process_start_time_seconds {$startTime}.0\n",
                200,
            ),
        ]);

        $result = $this->monitor->fetchMetrics();

        $this->assertTrue($result['metrics_available']);
        // Uptime should be approximately 3600 seconds (within 5 seconds tolerance)
        $this->assertGreaterThanOrEqual(3595, $result['uptime_seconds']);
        $this->assertLessThanOrEqual(3605, $result['uptime_seconds']);
    }
}
