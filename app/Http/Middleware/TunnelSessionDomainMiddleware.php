<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\TunnelSettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TunnelSessionDomainMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tunnel.enabled', false)) {
            return $next($request);
        }

        try {
            $settings = app(TunnelSettingsRepository::class)->getSettings();
            $hostname = trim((string) ($settings['hostname'] ?? ''));
            if ($hostname === '') {
                return $next($request);
            }

            $host = parse_url(
                str_starts_with($hostname, 'http') ? $hostname : 'https://'.$hostname,
                PHP_URL_HOST
            ) ?: $hostname;

            // Inject Referer when both Origin and Referer are absent so that
            // Sanctum's fromFrontend() recognises the request as stateful.
            // We detect tunnel requests via Cloudflare headers since the Host
            // header is rewritten by cloudflared to the local Herd hostname.
            $origin = $request->headers->get('Origin');
            $referer = $request->headers->get('Referer');

            if ($origin === null && $referer === null && $this->isTunnelRequest($request)) {
                $request->headers->set('Referer', 'https://'.$host.'/');
            }
        } catch (\Throwable) {
        }

        return $next($request);
    }

    /**
     * Detect whether the request arrived via Cloudflare Tunnel.
     */
    private function isTunnelRequest(Request $request): bool
    {
        return $request->headers->has('Cf-Connecting-Ip')
            || $request->headers->has('Cf-Ray');
    }
}
