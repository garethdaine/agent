<?php

declare(strict_types=1);

namespace App\Jobs\Tunnel;

use App\Repositories\TunnelSettingsRepository;
use App\Services\Tunnel\CloudflaredService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

class TunnelRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120, 300];

    public int $timeout = 0;

    public function __construct()
    {
        $this->onQueue('tunnel');
    }

    private const RESTART_ATTEMPTS_KEY = 'tunnel:restart_attempts';

    private const RESTART_WINDOW_SECONDS = 600; // 10 minutes

    private const MAX_RESTART_ATTEMPTS = 5;

    public function handle(CloudflaredService $service, TunnelSettingsRepository $settings): void
    {
        $status = $settings->getStatus();

        if ($status === 'unconfigured') {
            throw new RuntimeException('Tunnel is unconfigured. Configure tunnel settings before starting.');
        }

        $tunnelSettings = $settings->getSettings();
        $uuid = $tunnelSettings['tunnel_uuid'] ?? '';
        $originUrl = $tunnelSettings['origin_url'] ?? 'http://localhost:8000';
        $protocol = $tunnelSettings['protocol'] ?? 'http2';
        $ipAllowlist = $tunnelSettings['ip_allowlist'] ?? [];
        $hostname = $tunnelSettings['hostname'] ?? null;

        $built = $service->buildRunCommand($uuid, $originUrl, $protocol, $ipAllowlist ?: null, null, $hostname);
        $command = $built['command'];

        $settings->updateStatus('active');

        $result = Process::forever()->run($command);

        if ($result->exitCode() === 0) {
            $settings->updateStatus('stopped');
        } else {
            throw new RuntimeException('Tunnel process exited with code '.$result->exitCode().': '.$result->errorOutput());
        }
    }

    public function failed(Throwable $exception): void
    {
        $now = time();
        $settings = app(TunnelSettingsRepository::class);

        $settings->update(['last_error' => $exception->getMessage()], 'stopped');

        Redis::rpush(self::RESTART_ATTEMPTS_KEY, (string) $now);

        $attempts = Redis::lrange(self::RESTART_ATTEMPTS_KEY, 0, -1);
        $cutoff = $now - self::RESTART_WINDOW_SECONDS;

        $recentAttempts = array_filter($attempts, fn ($ts) => (int) $ts >= $cutoff);

        Redis::del(self::RESTART_ATTEMPTS_KEY);
        if (! empty($recentAttempts)) {
            Redis::rpush(self::RESTART_ATTEMPTS_KEY, ...array_values($recentAttempts));
        }

        if (count($recentAttempts) >= self::MAX_RESTART_ATTEMPTS) {
            $settings->update(['last_error' => $exception->getMessage()], 'error');

            Log::warning('Tunnel process exceeded maximum restart attempts', [
                'attempts' => count($recentAttempts),
                'window_seconds' => self::RESTART_WINDOW_SECONDS,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
