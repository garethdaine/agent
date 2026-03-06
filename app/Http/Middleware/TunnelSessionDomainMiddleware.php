<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\TunnelSettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TunnelSessionDomainMiddleware
{
    private const TUNNEL_HOST_COOKIE = 'laravel_tunnel_host';

    public function __construct(private readonly TunnelSettingsRepository $tunnelSettings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tunnel.enabled', false)) {
            return $next($request);
        }

        $host = null;
        $isTunnelRequest = false;

        try {
            $settings = $this->tunnelSettings->getSettings();
            $hostname = trim((string) ($settings['hostname'] ?? ''));
            if ($hostname === '') {
                return $next($request);
            }

            $host = parse_url(
                str_starts_with($hostname, 'http') ? $hostname : 'https://'.$hostname,
                PHP_URL_HOST
            ) ?: $hostname;

            $origin = $request->headers->get('Origin', '');
            $referer = $request->headers->get('Referer', '');
            $tunnelCookie = $request->cookie(self::TUNNEL_HOST_COOKIE);

            $isTunnelRequest = str_contains($origin, $host)
                || str_contains($referer, $host)
                || $tunnelCookie === $host;

            if ($isTunnelRequest) {
                config(['session.domain' => $host]);
            }
        } catch (\Throwable) {
        }

        $response = $next($request);

        if ($isTunnelRequest && $host !== null) {
            $response->cookie(
                self::TUNNEL_HOST_COOKIE,
                $host,
                now()->addDays(30)->timestamp,
                '/',
                $host,
                true,
                true,
                false,
                'lax'
            );
        }

        return $response;
    }
}
