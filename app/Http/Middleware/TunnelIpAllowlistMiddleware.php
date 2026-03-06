<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\TunnelSettingsRepository;
use App\Support\Agent\ErrorEnvelope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TunnelIpAllowlistMiddleware
{
    public function __construct(private readonly TunnelSettingsRepository $repository) {}

    public function handle(Request $request, Closure $next): Response
    {
        $setting = $this->repository->get();

        if (! $setting->isActive()) {
            return $next($request);
        }

        $allowlist = $setting->getIpAllowlist();

        if (empty($allowlist)) {
            return $next($request);
        }

        $clientIp = $request->header('CF-Connecting-IP') ?? $request->ip();

        foreach ($allowlist as $cidr) {
            if ($this->cidrMatch($clientIp, $cidr)) {
                return $next($request);
            }
        }

        return ErrorEnvelope::make('IP_BLOCKED', 'Your IP address is not in the tunnel allowlist.', 403);
    }

    private function cidrMatch(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);

        if (! is_numeric($bits)) {
            return false;
        }

        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $byteLen = strlen($ipBin);
        $totalBits = $byteLen * 8;

        if ($bits < 0 || $bits > $totalBits) {
            return false;
        }

        // Build bitmask
        $mask = str_repeat("\xff", intdiv($bits, 8));
        $remainder = $bits % 8;
        if ($remainder > 0) {
            $mask .= chr(0xFF << (8 - $remainder) & 0xFF);
        }
        $mask = str_pad($mask, $byteLen, "\x00");

        return ($ipBin & $mask) === ($subnetBin & $mask);
    }
}
