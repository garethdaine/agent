<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Agent\ProcessManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AgentRestartCommand extends Command
{
    protected $signature = 'agent:restart
        {--service=* : Specific services to restart}
        {--exclude-web : Exclude web server from restart}
        {--stop-only : Only stop services, do not start}
        {--timeout=30 : Graceful shutdown timeout in seconds}';

    protected $description = 'Graceful restart of the Agent runtime stack';

    /**
     * Services managed by this command with their command signatures.
     *
     * @var array<string, array{command: string, description: string, depends_on: array<string>, dev_only: bool}>
     */
    private const SERVICES = [
        'horizon' => [
            'command' => 'php artisan horizon',
            'description' => 'Laravel Horizon (queue supervisor)',
            'depends_on' => [],
            'dev_only' => false,
        ],
        'reverb' => [
            'command' => 'php artisan reverb:start',
            'description' => 'Laravel Reverb (WebSocket server)',
            'depends_on' => [],
            'dev_only' => false,
        ],
        'scheduler' => [
            'command' => 'php artisan schedule:work',
            'description' => 'Laravel Scheduler',
            'depends_on' => [],
            'dev_only' => false,
        ],
        'messenger-gateway' => [
            'command' => 'php artisan agent:messenger-gateway',
            'description' => 'Messenger gateway (Slack Socket Mode, Telegram long-poll, Discord Gateway)',
            'depends_on' => [],
            'dev_only' => false,
        ],
        'serve' => [
            'command' => 'php artisan serve',
            'description' => 'Laravel Development Server',
            'depends_on' => [],
            'dev_only' => true,
        ],
        'vite' => [
            'command' => 'npm run dev',
            'description' => 'Vite Development Server',
            'depends_on' => [],
            'dev_only' => true,
        ],
    ];

    public function handle(ProcessManager $processManager): int
    {
        $this->info('=== Agent Runtime Restart ===');
        $this->newLine();

        $timeout = (int) $this->option('timeout');
        $stopOnly = (bool) $this->option('stop-only');
        $excludeWeb = (bool) $this->option('exclude-web');

        // Determine which services to manage
        $requestedServices = $this->option('service');
        $services = $this->resolveServices($requestedServices, $excludeWeb);

        if (empty($services)) {
            $this->warn('No services to manage.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Services: %s',
            implode(', ', array_keys($services))
        ));
        $this->newLine();

        // Step 1: Stop services (in reverse dependency order)
        $this->info('[1/2] Stopping services...');
        $stopResults = $this->stopServices($services, $processManager, $timeout);
        $this->displayResults($stopResults, 'stop');
        $this->newLine();

        if ($stopOnly) {
            $this->info('Stop-only mode. Services stopped.');

            return $this->evaluateResults($stopResults);
        }

        // Step 2: Start services (in dependency order)
        $this->info('[2/2] Starting services...');
        $startResults = $this->startServices($services, $processManager);
        $this->displayResults($startResults, 'start');
        $this->newLine();

        // Log the restart event
        $this->logRestartEvent($services, $stopResults, $startResults);

        // Final status
        $this->info('=== Restart Complete ===');
        $this->displayServiceStatus($services, $processManager);

        return $this->evaluateResults(array_merge($stopResults, $startResults));
    }

    /**
     * @param  array<string>|string  $requestedServices
     * @return array<string, array{command: string, description: string, depends_on: array<string>, dev_only: bool}>
     */
    private function resolveServices(array|string $requestedServices, bool $excludeWeb): array
    {
        $includeDevServices = config('agent_restart.include_npm_dev', false);
        $includeWebServer = config('agent_restart.include_web_server', false);

        // Normalize requested services
        $requested = [];
        if (! empty($requestedServices)) {
            foreach ((array) $requestedServices as $service) {
                if (str_contains($service, ',')) {
                    $requested = array_merge($requested, explode(',', $service));
                } else {
                    $requested[] = $service;
                }
            }
            $requested = array_map('trim', array_map('strtolower', $requested));
            $requested = array_filter($requested);
        }

        $services = [];

        foreach (self::SERVICES as $name => $config) {
            // Skip dev-only services if not enabled
            if ($config['dev_only']) {
                if ($name === 'serve' && (! $includeWebServer || $excludeWeb)) {
                    continue;
                }
                if ($name === 'vite' && ! $includeDevServices) {
                    continue;
                }
            }

            // If specific services requested, filter to those
            if (! empty($requested) && ! in_array($name, $requested, true)) {
                continue;
            }

            $services[$name] = $config;
        }

        // Sort by dependencies (simple topological sort for our flat dependency graph)
        return $this->sortByDependencies($services);
    }

    /**
     * @param  array<string, array{command: string, description: string, depends_on: array<string>, dev_only: bool}>  $services
     * @return array<string, array{command: string, description: string, depends_on: array<string>, dev_only: bool}>
     */
    private function sortByDependencies(array $services): array
    {
        // For now, our services have no inter-dependencies, so just return as-is
        // If dependencies are added later, implement a proper topological sort
        return $services;
    }

    /**
     * @param  array<string, array{command: string, description: string, depends_on: array<string>, dev_only: bool}>  $services
     * @return array<string, array{success: bool, message: string}>
     */
    private function stopServices(array $services, ProcessManager $processManager, int $timeout): array
    {
        $results = [];

        // Stop in reverse order (for dependency handling)
        $serviceNames = array_reverse(array_keys($services));

        foreach ($serviceNames as $name) {
            $config = $services[$name];
            $this->line(sprintf('  Stopping %s...', $name));

            try {
                $commandSignature = $this->getCommandSignature($name, $config['command']);
                $stopped = $processManager->stopService($name, $commandSignature, $timeout);

                if ($stopped) {
                    $results[$name] = [
                        'success' => true,
                        'message' => 'Stopped successfully',
                    ];
                } else {
                    $results[$name] = [
                        'success' => false,
                        'message' => 'Failed to stop (may require manual intervention)',
                    ];
                }
            } catch (\Throwable $e) {
                $results[$name] = [
                    'success' => false,
                    'message' => sprintf('Error: %s', $e->getMessage()),
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, array{command: string, description: string, depends_on: array<string>, dev_only: bool}>  $services
     * @return array<string, array{success: bool, message: string, pid?: int}>
     */
    private function startServices(array $services, ProcessManager $processManager): array
    {
        $results = [];

        foreach ($services as $name => $config) {
            $this->line(sprintf('  Starting %s...', $name));

            try {
                // Verify service isn't already running
                $commandSignature = $this->getCommandSignature($name, $config['command']);
                $existingPid = $processManager->findByCommand($commandSignature);

                if ($existingPid !== null && $processManager->isRunning($existingPid)) {
                    $results[$name] = [
                        'success' => true,
                        'message' => sprintf('Already running (PID: %d)', $existingPid),
                        'pid' => $existingPid,
                    ];

                    continue;
                }

                // Start the service
                $pid = $this->startService($name, $config['command'], $processManager);

                // Brief pause to let the service initialize
                usleep(500_000); // 500ms

                // Verify it's running (captured PID may be the wrapper shell; resolve actual process if needed)
                if (! $processManager->isRunning($pid)) {
                    $commandSignature = $this->getCommandSignature($name, $config['command']);
                    $foundPid = $processManager->findByCommand($commandSignature);
                    if ($foundPid !== null) {
                        $pid = $foundPid;
                    }
                }

                if ($processManager->isRunning($pid)) {
                    $processManager->writePidFile($name, $pid);
                    $results[$name] = [
                        'success' => true,
                        'message' => sprintf('Started (PID: %d)', $pid),
                        'pid' => $pid,
                    ];
                } else {
                    $results[$name] = [
                        'success' => false,
                        'message' => sprintf(
                            'Started but process exited immediately. Run manually to see errors: %s',
                            $config['command']
                        ),
                    ];
                }
            } catch (\Throwable $e) {
                $results[$name] = [
                    'success' => false,
                    'message' => sprintf('Error: %s', $e->getMessage()),
                ];
            }
        }

        return $results;
    }

    private function startService(string $name, string $command, ProcessManager $processManager): int
    {
        $commandSignature = $this->getCommandSignature($name, $command);
        $basePath = escapeshellarg(base_path());

        // Start the real process in the background, then resolve its PID via pgrep so we track
        // the actual service process (not the wrapper shell which exits immediately).
        $startAndResolvePid = sprintf(
            'cd %s && nohup %s > /dev/null 2>&1 & sleep 0.5 && pgrep -f %s 2>/dev/null | head -1',
            $basePath,
            $command,
            escapeshellarg($commandSignature)
        );

        $result = Process::run($startAndResolvePid);

        if (! $result->successful()) {
            throw new \RuntimeException(sprintf(
                'Failed to start %s: %s',
                $name,
                $result->errorOutput()
            ));
        }

        $pid = (int) trim($result->output());

        if ($pid <= 0) {
            usleep(200_000); // 200ms
            $foundPid = $processManager->findByCommand($commandSignature);

            if ($foundPid !== null) {
                return $foundPid;
            }

            throw new \RuntimeException(sprintf(
                'Could not determine PID for %s',
                $name
            ));
        }

        return $pid;
    }

    /**
     * Get a unique signature for finding a process.
     */
    private function getCommandSignature(string $name, string $command): string
    {
        // Use a pattern that uniquely identifies this service
        return match ($name) {
            'horizon' => 'artisan horizon',
            'reverb' => 'artisan reverb:start',
            'scheduler' => 'artisan schedule:work',
            'serve' => 'artisan serve',
            'vite' => 'vite',
            default => $command,
        };
    }

    /**
     * @param  array<string, array{success: bool, message: string, pid?: int}>  $results
     */
    private function displayResults(array $results, string $action): void
    {
        foreach ($results as $service => $result) {
            $icon = $result['success']
                ? '<fg=green>[OK]</>'
                : '<fg=red>[FAIL]</>';

            $this->line(sprintf('    %s %s: %s', $icon, $service, $result['message']));
        }
    }

    /**
     * @param  array<string, array{command: string, description: string, depends_on: array<string>, dev_only: bool}>  $services
     */
    private function displayServiceStatus(array $services, ProcessManager $processManager): void
    {
        $this->newLine();
        $this->info('Service Status:');

        foreach ($services as $name => $config) {
            $pid = $processManager->readPidFile($name);
            $isRunning = $pid !== null && $processManager->isRunning($pid);

            $icon = $isRunning
                ? '<fg=green>[RUNNING]</>'
                : '<fg=yellow>[STOPPED]</>';

            $pidInfo = $isRunning ? sprintf(' (PID: %d)', $pid) : '';

            $this->line(sprintf(
                '  %s %s - %s%s',
                $icon,
                $name,
                $config['description'],
                $pidInfo
            ));
        }
    }

    /**
     * @param  array<string, array{success: bool, message: string}>  $results
     */
    private function evaluateResults(array $results): int
    {
        foreach ($results as $result) {
            if (! $result['success']) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{command: string, description: string, depends_on: array<string>, dev_only: bool}>  $services
     * @param  array<string, array{success: bool, message: string}>  $stopResults
     * @param  array<string, array{success: bool, message: string, pid?: int}>  $startResults
     */
    private function logRestartEvent(array $services, array $stopResults, array $startResults): void
    {
        $context = [
            'services' => array_keys($services),
            'stop_results' => $stopResults,
            'start_results' => $startResults,
            'timestamp' => now()->toIso8601String(),
        ];

        Log::info('Agent runtime restart completed', $context);
    }
}
