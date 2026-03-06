<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TunnelFeatureGate
{
    public function __construct(private readonly FeatureFlagManager $featureFlags) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->featureFlags->isEnabled(FeatureFlagManager::TUNNEL_ENABLED)) {
            return ErrorEnvelope::make('FEATURE_DISABLED', 'Cloudflare Tunnel is not enabled.', 404);
        }

        return $next($request);
    }
}
