<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OutageAutoProtect
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('outage.auto_protect_enabled', false)) {
            return $next($request);
        }

        $message = config('outage.message', 'System is in maintenance mode. Please try again later.');

        if ($request->expectsJson()) {
            return response()->json(['error' => ['message' => $message]], 503);
        }

        return response($message, 503);
    }
}
