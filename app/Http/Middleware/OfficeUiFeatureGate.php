<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OfficeUiFeatureGate
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('agent.office_3d_enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
