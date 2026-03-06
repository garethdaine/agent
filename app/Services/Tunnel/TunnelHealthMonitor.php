<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use App\Repositories\TunnelSettingsRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class TunnelHealthMonitor
{
    public function __construct(
        private TunnelSettingsRepository $repository,
        private CloudflaredService $cloudflaredService,
    ) {}

    /**
     * @return array{alive: bool, pid: ?int}
     */
    public function checkProcessStatus(): array
    {
        $result = Process::run("pgrep -f 'cloudflared tunnel run'");

        if ($result->successful()) {
            $pid = (int) trim($result->output());

            return [
                'alive' => true,
                'pid' => $pid > 0 ? $pid : null,
            ];
        }

        return [
            'alive' => false,
            'pid' => null,
        ];
    }

    /**
     * @return array{connections: int, error_count: int, uptime_seconds: int, metrics_available: bool}
     */
    public function fetchMetrics(?int $port = null): array
    {
        $port = $port ?? (int) config('tunnel.metrics_port', 20241);

        $defaults = [
            'metrics_available' => false,
            'connections' => 0,
            'error_count' => 0,
            'uptime_seconds' => 0,
        ];

        try {
            $response = Http::timeout(2)->get("http://localhost:{$port}/metrics");
        } catch (ConnectionException) {
            return $defaults;
        }

        if (! $response->successful()) {
            return $defaults;
        }

        $body = $response->body();

        $connections = 0;
        if (preg_match('/^cloudflared_tunnel_connections\s+(\d+)/m', $body, $matches)) {
            $connections = (int) $matches[1];
        }

        $errorCount = 0;
        if (preg_match('/^cloudflared_tunnel_request_errors_total\s+(\d+)/m', $body, $matches)) {
            $errorCount = (int) $matches[1];
        }

        $uptimeSeconds = 0;
        if (preg_match('/^process_start_time_seconds\s+([\d.]+)/m', $body, $matches)) {
            $startTime = (int) floor((float) $matches[1]);
            $uptimeSeconds = max(0, time() - $startTime);
        }

        return [
            'metrics_available' => true,
            'connections' => $connections,
            'error_count' => $errorCount,
            'uptime_seconds' => $uptimeSeconds,
        ];
    }

    /**
     * @return array{status: string, hostname: string, process: array, metrics: array, version: array}
     */
    public function getHealth(): array
    {
        $process = $this->checkProcessStatus();
        $metrics = $this->fetchMetrics();
        $version = $this->cloudflaredService->isVersionCompatible();
        $status = $this->repository->getStatus();
        $settings = $this->repository->getSettings();

        return [
            'status' => $status,
            'hostname' => $settings['hostname'] ?? '',
            'process' => $process,
            'metrics' => $metrics,
            'version' => $version,
        ];
    }
}
