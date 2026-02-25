<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DelegationUiFeatureGate
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('delegation.ui_enabled', false)) {
            abort(403, 'Delegation UI is not enabled');
        }

        return $next($request);
    }
}
