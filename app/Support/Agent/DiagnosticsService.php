<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Messenger\Gateway\Enums\WorkerHealthStatus;
use App\Models\ConnectorAccount;
use App\Models\SchedulerHeartbeat;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class DiagnosticsService
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARN = 'warn';

    public const STATUS_ERROR = 'error';

    /**
     * Run diagnostics checks. Returns list of checks with status, message, and optional fix.
     *
     * @return array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>
     */
    public function run(): array
    {
        $checks = [];

        $this->checkAppKey($checks);
        $this->checkDatabase($checks);
        $this->checkRedis($checks);
        $this->checkQueue($checks);
        $this->checkScheduler($checks);
        $this->checkMessenger($checks);
        $this->checkStorage($checks);
        $this->checkRuntimeConfig($checks);

        return $checks;
    }

    /**
     * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
     */
    private function checkAppKey(array &$checks): void
    {
        $key = Config::get('app.key');
        if (empty($key) || $key === 'base64:') {
            $checks[] = [
                'check_id' => 'app.key',
                'status' => self::STATUS_ERROR,
                'message' => 'Application key is not set.',
                'fix' => 'Run php artisan key:generate',
                'latency_ms' => null,
            ];
        } else {
            $checks[] = [
                'check_id' => 'app.key',
                'status' => self::STATUS_OK,
                'message' => 'Application key is set.',
                'fix' => null,
                'latency_ms' => null,
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
     */
    private function checkDatabase(array &$checks): void
    {
        $start = hrtime(true);
        try {
            DB::select('SELECT 1');
            $checks[] = [
                'check_id' => 'database',
                'status' => self::STATUS_OK,
                'message' => 'Database connection OK.',
                'fix' => null,
                'latency_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'check_id' => 'database',
                'status' => self::STATUS_ERROR,
                'message' => 'Database connection failed: '.$e->getMessage(),
                'fix' => 'Check DB_* env and database server.',
                'latency_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
     */
    private function checkRedis(array &$checks): void
    {
        $start = hrtime(true);
        try {
            Redis::connection()->ping();
            $checks[] = [
                'check_id' => 'redis',
                'status' => self::STATUS_OK,
                'message' => 'Redis connection OK.',
                'fix' => null,
                'latency_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'check_id' => 'redis',
                'status' => self::STATUS_ERROR,
                'message' => 'Redis connection failed: '.$e->getMessage(),
                'fix' => 'Check REDIS_* env and Redis server. Required for queue and cache.',
                'latency_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
     */
    private function checkQueue(array &$checks): void
    {
        $start = hrtime(true);
        try {
            $size = Queue::size('agent');
            $checks[] = [
                'check_id' => 'queue',
                'status' => self::STATUS_OK,
                'message' => "Queue 'agent' reachable (size: {$size}).",
                'fix' => null,
                'latency_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'check_id' => 'queue',
                'status' => self::STATUS_ERROR,
                'message' => 'Queue check failed: '.$e->getMessage(),
                'fix' => 'Ensure Horizon or queue:work is running; check Redis.',
                'latency_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
     */
    private function checkScheduler(array &$checks): void
    {
        $heartbeat = SchedulerHeartbeat::query()->where('source', 'scheduler_dispatch')->first();
        if ($heartbeat === null) {
            $checks[] = [
                'check_id' => 'scheduler',
                'status' => self::STATUS_WARN,
                'message' => 'No scheduler heartbeat found. Schedule may not be running.',
                'fix' => 'Run php artisan schedule:work in a persistent process.',
                'latency_ms' => null,
            ];

            return;
        }

        $ageSeconds = $heartbeat->last_seen_at?->diffInSeconds(now()) ?? 999;
        if ($ageSeconds > 300) {
            $checks[] = [
                'check_id' => 'scheduler',
                'status' => self::STATUS_ERROR,
                'message' => "Scheduler heartbeat is stale ({$ageSeconds}s ago).",
                'fix' => 'Restart php artisan schedule:work.',
                'latency_ms' => null,
            ];
        } elseif ($ageSeconds > 90) {
            $checks[] = [
                'check_id' => 'scheduler',
                'status' => self::STATUS_WARN,
                'message' => "Scheduler heartbeat is old ({$ageSeconds}s ago).",
                'fix' => 'Check schedule:work process.',
                'latency_ms' => null,
            ];
        } else {
            $checks[] = [
                'check_id' => 'scheduler',
                'status' => self::STATUS_OK,
                'message' => 'Scheduler heartbeat recent.',
                'fix' => null,
                'latency_ms' => null,
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
     */
    private function checkMessenger(array &$checks): void
    {
        $total = ConnectorAccount::count();
        $connected = ConnectorAccount::where('runtime_state', WorkerHealthStatus::Connected)->count();
        if ($total === 0) {
            $checks[] = [
                'check_id' => 'messenger',
                'status' => self::STATUS_OK,
                'message' => 'No messenger connectors configured.',
                'fix' => null,
                'latency_ms' => null,
            ];

            return;
        }
        if ($connected === $total) {
            $checks[] = [
                'check_id' => 'messenger',
                'status' => self::STATUS_OK,
                'message' => "Messenger: {$connected}/{$total} connectors connected.",
                'fix' => null,
                'latency_ms' => null,
            ];
        } elseif ($connected > 0) {
            $checks[] = [
                'check_id' => 'messenger',
                'status' => self::STATUS_WARN,
                'message' => "Messenger: {$connected}/{$total} connectors connected.",
                'fix' => 'Check Tools → Messenger and connector status.',
                'latency_ms' => null,
            ];
        } else {
            $checks[] = [
                'check_id' => 'messenger',
                'status' => self::STATUS_WARN,
                'message' => "Messenger: 0/{$total} connectors connected.",
                'fix' => 'Start gateway workers and check connector config.',
                'latency_ms' => null,
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
     */
    private function checkStorage(array &$checks): void
    {
        try {
            $path = 'diagnostics-'.bin2hex(random_bytes(8)).'.txt';
            Storage::disk('local')->put($path, 'ok');
            Storage::disk('local')->delete($path);
            $checks[] = [
                'check_id' => 'storage',
                'status' => self::STATUS_OK,
                'message' => 'Storage (local) is writable.',
                'fix' => null,
                'latency_ms' => null,
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'check_id' => 'storage',
                'status' => self::STATUS_ERROR,
                'message' => 'Storage write failed: '.$e->getMessage(),
                'fix' => 'Check storage/ permissions.',
                'latency_ms' => null,
            ];
        }
    }

    /**
     * @param  array<int, array{check_id: string, status: string, message: string, fix: string|null, latency_ms: int|null}>  $checks
     */
    private function checkRuntimeConfig(array &$checks): void
    {
        $mode = Config::get('runtime.default_mode', 'safe');
        $checks[] = [
            'check_id' => 'runtime.default_mode',
            'status' => self::STATUS_OK,
            'message' => "Runtime default mode: {$mode}.",
            'fix' => null,
            'latency_ms' => null,
        ];
    }
}
