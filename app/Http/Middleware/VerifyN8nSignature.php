<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('n8n.webhook_secret');

        if ($secret === null || $secret === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook authentication is not configured.',
            ], 403);
        }

        $signature = $request->header('X-N8N-Signature');

        if ($signature === null || $signature === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Missing webhook signature.',
            ], 401);
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        return $next($request);
    }
}
