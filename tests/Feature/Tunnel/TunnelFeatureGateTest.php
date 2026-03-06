<?php

declare(strict_types=1);

namespace Tests\Feature\Tunnel;

use App\Http\Middleware\TunnelFeatureGate;
use App\Models\AgentFeatureSetting;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class TunnelFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_404_when_tunnel_feature_disabled(): void
    {
        $middleware = app(TunnelFeatureGate::class);
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('FEATURE_DISABLED', $data['error']['code']);
    }

    public function test_passes_through_when_tunnel_feature_enabled(): void
    {
        AgentFeatureSetting::create([
            'key' => FeatureFlagManager::TUNNEL_ENABLED,
            'is_enabled' => true,
        ]);

        $middleware = app(TunnelFeatureGate::class);
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
