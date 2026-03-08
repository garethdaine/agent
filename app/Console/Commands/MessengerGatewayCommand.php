<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Messenger\Gateway\MessengerGatewayManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use React\EventLoop\LoopInterface;

/**
 * Artisan command to run the messenger gateway supervisor.
 *
 * This command runs a long-lived PHP process that manages gateway workers
 * for local-mode messenger connectors (Slack Socket Mode, Telegram polling,
 * Discord Gateway).
 *
 * Designed to run alongside Horizon as a separate supervised process.
 *
 * Assumptions:
 * - ReactPHP EventLoop is available via container
 * - MessengerGatewayManager is configured and can boot workers
 * - Process will be managed by supervisor/systemd in production
 *
 * Signal Handling:
 * - SIGTERM: Graceful shutdown with drain timeout
 * - SIGINT: Same as SIGTERM (Ctrl+C)
 * - SIGUSR1: Output health status table
 *
 * Exit Codes:
 * - 0: Clean shutdown
 * - 1: Error during startup or drain timeout exceeded
 */
class MessengerGatewayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:messenger-gateway';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the messenger gateway supervisor for local-mode connectors';

    /**
     * Whether shutdown has been requested.
     */
    private bool $shouldShutdown = false;

    /**
     * Whether shutdown completed successfully.
     */
    private bool $shutdownClean = false;

    /**
     * The ReactPHP event loop.
     */
    private LoopInterface $loop;

    /**
     * The gateway manager instance.
     */
    private MessengerGatewayManager $manager;

    /**
     * Path to the lock file for preventing duplicate processes.
     */
    private string $lockFile;

    /**
     * Execute the console command.
     */
    public function handle(LoopInterface $loop, MessengerGatewayManager $manager): int
    {
        $this->loop = $loop;
        $this->manager = $manager;
        $this->lockFile = storage_path('framework/messenger-gateway.lock');

        $this->info('Starting Messenger Gateway...');

        try {
            // Attempt to boot all workers
            $manager->bootWorkers();
            $workerCount = $manager->getWorkerCount();
            $this->info("Workers booted: {$workerCount}");

            Log::info('Messenger Gateway started', [
                'worker_count' => $workerCount,
                'pid' => getmypid(),
            ]);
        } catch (\Throwable $e) {
            $this->error('Failed to boot workers: '.$e->getMessage());
            Log::error('Messenger Gateway failed to start', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }

        // Install signal handlers
        $this->installSignalHandlers();

        // Write lock file
        $this->writeLockFile();

        // Add periodic status output (every 60 seconds)
        $this->loop->addPeriodicTimer(60, function () {
            $this->outputBriefStatus();
        });

        // Run the event loop (blocks until stopped)
        $this->loop->run();

        // Cleanup lock file
        $this->removeLockFile();

        // Determine exit code based on shutdown state
        if ($this->manager->isShutdown() || $this->shutdownClean) {
            $this->info('Messenger Gateway stopped.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * Install signal handlers for graceful shutdown and health output.
     */
    private function installSignalHandlers(): void
    {
        // Only install signal handlers if pcntl is available
        if (! extension_loaded('pcntl')) {
            $this->warn('pcntl extension not available, signal handling disabled');

            return;
        }

        // SIGTERM - graceful shutdown (from supervisor/docker)
        $this->loop->addSignal(SIGTERM, function () {
            $this->initiateShutdown('SIGTERM');
        });

        // SIGINT - graceful shutdown (Ctrl+C)
        $this->loop->addSignal(SIGINT, function () {
            $this->initiateShutdown('SIGINT');
        });

        // SIGUSR1 - output health status (debug signal)
        $this->loop->addSignal(SIGUSR1, function () {
            $this->outputHealthStatus();
        });
    }

    /**
     * Initiate graceful shutdown sequence.
     */
    private function initiateShutdown(string $signal): void
    {
        if ($this->shouldShutdown) {
            // Already shutting down, ignore duplicate signals
            return;
        }

        $this->shouldShutdown = true;
        $timeout = (int) config('messenger.gateway.shutdown_timeout', 30);

        $this->warn("Shutdown signal received ({$signal}), draining workers...");

        Log::info('Messenger Gateway shutdown initiated', [
            'signal' => $signal,
            'timeout_seconds' => $timeout,
        ]);

        // Set timeout for forced exit
        $this->loop->addTimer($timeout, function () {
            if (! $this->shutdownClean) {
                $this->error('Drain timeout exceeded, forcing exit');
                Log::error('Messenger Gateway drain timeout exceeded');
                $this->shutdownClean = false;
                $this->loop->stop();
            }
        });

        // Initiate graceful shutdown
        try {
            $this->manager->shutdown();
            $this->info('All workers drained successfully');
            $this->shutdownClean = true;
        } catch (\Throwable $e) {
            $this->error('Error during shutdown: '.$e->getMessage());
            Log::error('Messenger Gateway shutdown error', [
                'error' => $e->getMessage(),
            ]);
            $this->shutdownClean = false;
        }

        // The manager->shutdown() already stops the loop
    }

    /**
     * Output brief status to console (periodic).
     */
    private function outputBriefStatus(): void
    {
        $workerCount = $this->manager->getWorkerCount();
        $this->line(sprintf(
            '[%s] Workers: %d active',
            now()->format('H:i:s'),
            $workerCount
        ));
    }

    /**
     * Output detailed health status table (SIGUSR1).
     */
    private function outputHealthStatus(): void
    {
        $this->info('Health Status Report:');

        $health = $this->manager->getHealthSummary();

        if (empty($health)) {
            $this->line('No workers currently running.');

            return;
        }

        $this->table(
            ['Connector', 'Provider', 'Status', 'Last Event'],
            $health
        );
    }

    /**
     * Write PID to lock file.
     */
    private function writeLockFile(): void
    {
        $pid = getmypid();
        if ($pid !== false) {
            file_put_contents($this->lockFile, (string) $pid);
        }
    }

    /**
     * Remove lock file on shutdown.
     */
    private function removeLockFile(): void
    {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
}
