<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Agent\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseValid
{
    public function __construct(
        private readonly LicenseService $license,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->license->shouldBypass()) {
            return $next($request);
        }

        if ($this->license->isValid()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('agent/api/*')) {
            return response()->json([
                'error' => [
                    'code' => 'LICENSE_INVALID',
                    'message' => 'A valid license is required to use this application.',
                    'details' => (object) [],
                ],
            ], 403);
        }

        return redirect()->route('license.required');
    }
}
