<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Models\AgentAuditLog;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\ConnectorAccount;
use App\Models\Runtime\RuntimeSession;
use App\Support\Agent\DiagnosticsService;
use Illuminate\Support\Facades\Redis;

class GetDebugStatsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(DiagnosticsService $diagnostics): array
    {
        $checks = $diagnostics->run();
        $healthSummary = [
            'total' => count($checks),
            'ok' => count(array_filter($checks, fn (array $c) => $c['status'] === DiagnosticsService::STATUS_OK)),
            'warn' => count(array_filter($checks, fn (array $c) => $c['status'] === DiagnosticsService::STATUS_WARN)),
            'error' => count(array_filter($checks, fn (array $c) => $c['status'] === DiagnosticsService::STATUS_ERROR)),
        ];

        $activeSessions = RuntimeSession::where('status', 'active')->count();
        $totalSessions = RuntimeSession::count();

        $activeJobs = AgentJob::enabled()->count();
        $recentRuns = AgentJobRun::where('created_at', '>=', now()->subHours(24))->count();
        $failedRuns24h = AgentJobRun::where('created_at', '>=', now()->subHours(24))
            ->where('status', 'failed')
            ->count();

        $connectorCount = ConnectorAccount::count();

        $recentEvents = AgentAuditLog::orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'action', 'actor_type', 'target_type', 'target_id', 'outcome', 'created_at']);

        $queueInfo = $this->getQueueInfo();

        return [
            'health' => [
                'summary' => $healthSummary,
                'checks' => $checks,
            ],
            'sessions' => [
                'active' => $activeSessions,
                'total' => $totalSessions,
            ],
            'jobs' => [
                'active_definitions' => $activeJobs,
                'runs_24h' => $recentRuns,
                'failed_24h' => $failedRuns24h,
            ],
            'connectors' => $connectorCount,
            'queue' => $queueInfo,
            'recent_events' => $recentEvents,
            'environment' => [
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'db_driver' => config('database.default'),
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getQueueInfo(): array
    {
        try {
            $connection = config('queue.default');
            if ($connection === 'redis') {
                $prefix = config('database.redis.options.prefix', '');
                $queues = ['default', 'agent', 'messenger-default'];
                $sizes = [];
                foreach ($queues as $queue) {
                    $key = $prefix.'queues:'.$queue;
                    $sizes[$queue] = (int) Redis::llen($key);
                }

                return ['driver' => 'redis', 'sizes' => $sizes];
            }

            return ['driver' => $connection, 'sizes' => []];
        } catch (\Throwable) {
            return ['driver' => config('queue.default'), 'sizes' => [], 'error' => 'Could not read queue sizes'];
        }
    }
}
