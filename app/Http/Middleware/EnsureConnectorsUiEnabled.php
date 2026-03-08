<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Agent\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConnectorsUiEnabled
{
    public function __construct(private readonly FeatureFlagManager $featureFlags) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::CONNECTORS_ENABLED)
            || ! $this->featureFlags->enabled(FeatureFlagManager::CONNECTORS_UI_ENABLED)) {
            abort(404);
        }

        return $next($request);
    }
}
