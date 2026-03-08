<?php

declare(strict_types=1);

namespace App\Support\Agent;

use Illuminate\Support\Facades\Log;

class ServiceManagerService
{
    /**
     * Services exposed to the UI with user-friendly labels.
     *
     * @var array<string, array{label: string, description: string, command: string, signature: string, critical: bool}>
     */
    private const SERVICES = [
        'horizon' => [
            'label' => 'Task Queue',
            'description' => 'Processes background tasks such as job runs, plan generation, and notifications.',
            'command' => 'php artisan horizon',
            'signature' => 'artisan horizon',
            'critical' => true,
        ],
        'reverb' => [
            'label' => 'Live Updates',
            'description' => 'Provides real-time updates to the dashboard and active sessions.',
            'command' => 'php artisan reverb:start',
            'signature' => 'artisan reverb:start',
            'critical' => false,
        ],
        'scheduler' => [
            'label' => 'Scheduler',
            'description' => 'Runs scheduled jobs and periodic maintenance tasks.',
            'command' => 'php artisan schedule:work',
            'signature' => 'artisan schedule:work',
            'critical' => true,
        ],
        'messenger-gateway' => [
            'label' => 'Messenger Gateway',
            'description' => 'Handles connections to Slack, Telegram, Discord, and other messaging platforms.',
            'command' => 'php artisan agent:messenger-gateway',
            'signature' => 'agent:messenger-gateway',
            'critical' => false,
        ],
    ];

    public function __construct(
        private readonly ProcessManager $processManager,
    ) {}

    /**
     * Get the status of all managed services.
     *
     * @return array<string, array{key: string, label: string, description: string, status: string, critical: bool, pid: int|null}>
     */
    public function status(): array
    {
        $result = [];

        foreach (self::SERVICES as $key => $config) {
            $pid = $this->resolveRunningPid($key, $config['signature']);

            $result[$key] = [
                'key' => $key,
                'label' => $config['label'],
                'description' => $config['description'],
                'status' => $pid !== null ? 'running' : 'stopped',
                'critical' => $config['critical'],
                'pid' => $pid,
            ];
        }

        return $result;
    }

    /**
     * Restart a specific service by key.
     *
     * @return array{success: bool, message: string}
     */
    public function restart(string $serviceKey): array
    {
        if (! isset(self::SERVICES[$serviceKey])) {
            return [
                'success' => false,
                'message' => 'Unknown service.',
            ];
        }

        $config = self::SERVICES[$serviceKey];

        try {
            // Stop the service
            $this->processManager->stopService($serviceKey, $config['signature'], 15);

            // Brief pause before restart
            usleep(500_000);

            // Start the service
            $pid = $this->startService($serviceKey, $config);

            // Verify it came up
            usleep(1_000_000);

            $runningPid = $this->resolveRunningPid($serviceKey, $config['signature']);

            if ($runningPid !== null) {
                Log::info('Service restarted via UI', [
                    'service' => $serviceKey,
                    'pid' => $runningPid,
                ]);

                return [
                    'success' => true,
                    'message' => "{$config['label']} restarted successfully.",
                ];
            }

            return [
                'success' => false,
                'message' => "{$config['label']} was started but exited immediately. Check logs for errors.",
            ];
        } catch (\Throwable $e) {
            Log::error('Service restart failed via UI', [
                'service' => $serviceKey,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => "Failed to restart {$config['label']}: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Start a service and record its PID.
     */
    private function startService(string $key, array $config): int
    {
        $basePath = escapeshellarg(base_path());

        $startCmd = sprintf(
            'cd %s && nohup %s > /dev/null 2>&1 & sleep 0.5 && pgrep -f %s 2>/dev/null | head -1',
            $basePath,
            $config['command'],
            escapeshellarg($config['signature'])
        );

        $result = \Illuminate\Support\Facades\Process::run($startCmd);

        $pid = (int) trim($result->output());

        if ($pid <= 0) {
            usleep(300_000);
            $foundPid = $this->processManager->findByCommand($config['signature']);

            if ($foundPid !== null) {
                $this->processManager->writePidFile($key, $foundPid);

                return $foundPid;
            }

            throw new \RuntimeException("Could not determine PID for {$config['label']}");
        }

        $this->processManager->writePidFile($key, $pid);

        return $pid;
    }

    /**
     * Resolve the actual running PID for a service (PID file first, then pgrep fallback).
     */
    private function resolveRunningPid(string $key, string $signature): ?int
    {
        // Try PID file
        $pid = $this->processManager->readPidFile($key);

        if ($pid !== null && $this->processManager->isRunning($pid)) {
            return $pid;
        }

        // Fallback to pgrep
        $pid = $this->processManager->findByCommand($signature);

        if ($pid !== null && $this->processManager->isRunning($pid)) {
            $this->processManager->writePidFile($key, $pid);

            return $pid;
        }

        return null;
    }
}
