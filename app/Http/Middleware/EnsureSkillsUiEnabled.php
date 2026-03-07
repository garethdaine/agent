<?php

namespace App\Http\Middleware;

use App\Support\Agent\FeatureFlagManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSkillsUiEnabled
{
    public function __construct(private readonly FeatureFlagManager $featureFlags) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->featureFlags->enabled(FeatureFlagManager::SKILLS_ENABLED)
            || ! $this->featureFlags->enabled(FeatureFlagManager::SKILLS_UI_ENABLED)) {
            return redirect('/tools')->with('error', 'Skills feature is not enabled.');
        }

        return $next($request);
    }
}
