<?php

declare(strict_types=1);

namespace Tests\Unit\Tunnel;

use App\Http\Middleware\TunnelIpAllowlistMiddleware;
use App\Models\TunnelSetting;
use App\Repositories\TunnelSettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

class TunnelIpAllowlistMiddlewareTest extends TestCase
{
    private function makeMiddleware(string $status = 'active', ?array $ipAllowlist = []): TunnelIpAllowlistMiddleware
    {
        $setting = new TunnelSetting;
        $setting->status = $status;
        $setting->settings = ['ip_allowlist' => $ipAllowlist];

        $repo = Mockery::mock(TunnelSettingsRepository::class);
        $repo->shouldReceive('get')->andReturn($setting);

        return new TunnelIpAllowlistMiddleware($repo);
    }

    private function makeRequest(string $ip = '1.2.3.4', ?string $cfHeader = null): Request
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
        ]);

        if ($cfHeader !== null) {
            $request->headers->set('CF-Connecting-IP', $cfHeader);
        }

        return $request;
    }

    private function passthrough(): \Closure
    {
        return fn () => new Response('OK');
    }

    public function test_passes_when_allowlist_empty(): void
    {
        $middleware = $this->makeMiddleware('active', []);
        $response = $middleware->handle($this->makeRequest(), $this->passthrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_when_allowlist_null(): void
    {
        $middleware = $this->makeMiddleware('active', null);
        $response = $middleware->handle($this->makeRequest(), $this->passthrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_when_tunnel_not_active(): void
    {
        $middleware = $this->makeMiddleware('stopped', ['10.0.0.0/8']);
        $response = $middleware->handle($this->makeRequest('1.2.3.4'), $this->passthrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_when_ip_matches_cidr(): void
    {
        $middleware = $this->makeMiddleware('active', ['10.0.0.0/8']);
        $response = $middleware->handle($this->makeRequest('10.1.2.3'), $this->passthrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_when_ip_matches_exact(): void
    {
        $middleware = $this->makeMiddleware('active', ['1.2.3.4/32']);
        $response = $middleware->handle($this->makeRequest('1.2.3.4'), $this->passthrough());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_blocks_when_ip_not_in_allowlist(): void
    {
        $middleware = $this->makeMiddleware('active', ['10.0.0.0/8']);
        $response = $middleware->handle($this->makeRequest('192.168.1.1'), $this->passthrough());

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_returns_403_with_error_envelope(): void
    {
        $middleware = $this->makeMiddleware('active', ['10.0.0.0/8']);
        $response = $middleware->handle($this->makeRequest('192.168.1.1'), $this->passthrough());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('IP_BLOCKED', $data['error']['code']);
        $this->assertStringContainsString('not in the tunnel allowlist', $data['error']['message']);
    }

    public function test_reads_cf_connecting_ip_header(): void
    {
        $middleware = $this->makeMiddleware('active', ['10.0.0.0/8']);
        $response = $middleware->handle(
            $this->makeRequest('192.168.1.1', '10.5.5.5'),
            $this->passthrough()
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_falls_back_to_request_ip_when_header_missing(): void
    {
        $middleware = $this->makeMiddleware('active', ['1.2.3.4/32']);
        $response = $middleware->handle(
            $this->makeRequest('1.2.3.4'),
            $this->passthrough()
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handles_multiple_cidr_ranges(): void
    {
        $middleware = $this->makeMiddleware('active', ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        // Matches second range
        $response = $middleware->handle($this->makeRequest('172.20.1.1'), $this->passthrough());
        $this->assertEquals(200, $response->getStatusCode());

        // Matches third range
        $response = $middleware->handle($this->makeRequest('192.168.5.10'), $this->passthrough());
        $this->assertEquals(200, $response->getStatusCode());

        // Matches none
        $response = $middleware->handle($this->makeRequest('8.8.8.8'), $this->passthrough());
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_handles_invalid_cidr_gracefully(): void
    {
        $middleware = $this->makeMiddleware('active', ['not-a-cidr', 'also/invalid']);
        $response = $middleware->handle($this->makeRequest('1.2.3.4'), $this->passthrough());

        $this->assertEquals(403, $response->getStatusCode());
    }
}
