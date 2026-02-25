<?php

namespace App\Support\Agent;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ProcessManager
{
    private const PID_DIRECTORY = 'pids';

    private Filesystem $storage;

    public function __construct()
    {
        $this->storage = Storage::disk('local');
        $this->ensurePidDirectory();
    }

    /**
     * Find a process by command signature.
     *
     * @return int|null The PID if found, null otherwise.
     */
    public function findByCommand(string $command): ?int
    {
        $result = Process::run(sprintf(
            "pgrep -f %s 2>/dev/null | head -1",
            escapeshellarg($command)
        ));

        if (! $result->successful()) {
            return null;
        }

        $pid = trim($result->output());

        return $pid !== '' ? (int) $pid : null;
    }

    /**
     * Find all PIDs matching a command pattern.
     *
     * @return array<int>
     */
    public function findAllByCommand(string $command): array
    {
        $result = Process::run(sprintf(
            "pgrep -f %s 2>/dev/null",
            escapeshellarg($command)
        ));

        if (! $result->successful()) {
            return [];
        }

        return array_filter(
            array_map(
                fn (string $line): int => (int) trim($line),
                explode("\n", trim($result->output()))
            ),
            fn (int $pid): bool => $pid > 0
        );
    }

    /**
     * Stop a process gracefully with SIGTERM, falling back to SIGKILL after timeout.
     */
    public function stop(int $pid, int $timeout = 30): bool
    {
        if (! $this->isRunning($pid)) {
            return true;
        }

        // Send SIGTERM for graceful shutdown
        $result = Process::run(sprintf('kill -TERM %d 2>/dev/null', $pid));

        if (! $result->successful()) {
            return ! $this->isRunning($pid);
        }

        // Wait for graceful shutdown
        $startTime = time();
        while ($this->isRunning($pid)) {
            if ((time() - $startTime) >= $timeout) {
                // Force kill after timeout
                Process::run(sprintf('kill -KILL %d 2>/dev/null', $pid));

                usleep(100_000); // 100ms

                return ! $this->isRunning($pid);
            }

            usleep(100_000); // 100ms
        }

        return true;
    }

    /**
     * Start a command and return its PID.
     */
    public function start(string $command): int
    {
        $result = Process::run(sprintf(
            'nohup %s > /dev/null 2>&1 & echo $!',
            $command
        ));

        if (! $result->successful()) {
            throw new \RuntimeException(sprintf(
                'Failed to start command: %s. Error: %s',
                $command,
                $result->errorOutput()
            ));
        }

        $pid = (int) trim($result->output());

        if ($pid <= 0) {
            throw new \RuntimeException(sprintf(
                'Invalid PID returned when starting command: %s',
                $command
            ));
        }

        return $pid;
    }

    /**
     * Check if a process is running.
     */
    public function isRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $result = Process::run(sprintf('kill -0 %d 2>/dev/null', $pid));

        return $result->successful();
    }

    /**
     * Write a PID file for a service.
     */
    public function writePidFile(string $service, int $pid): void
    {
        $path = $this->getPidFilePath($service);
        $this->storage->put($path, (string) $pid);
    }

    /**
     * Read a PID from a service's PID file.
     */
    public function readPidFile(string $service): ?int
    {
        $path = $this->getPidFilePath($service);

        if (! $this->storage->exists($path)) {
            return null;
        }

        $content = trim($this->storage->get($path) ?? '');

        if ($content === '') {
            return null;
        }

        $pid = (int) $content;

        return $pid > 0 ? $pid : null;
    }

    /**
     * Delete a service's PID file.
     */
    public function deletePidFile(string $service): void
    {
        $path = $this->getPidFilePath($service);

        if ($this->storage->exists($path)) {
            $this->storage->delete($path);
        }
    }

    /**
     * Get the command line for a running process.
     */
    public function getProcessCommand(int $pid): ?string
    {
        $result = Process::run(sprintf('ps -p %d -o command= 2>/dev/null', $pid));

        if (! $result->successful()) {
            return null;
        }

        $command = trim($result->output());

        return $command !== '' ? $command : null;
    }

    /**
     * Stop a service by name, using PID file if available, otherwise by command signature.
     */
    public function stopService(string $service, string $commandSignature, int $timeout = 30): bool
    {
        // Try PID file first
        $pid = $this->readPidFile($service);

        if ($pid !== null && $this->isRunning($pid)) {
            // Verify the process matches the expected command signature
            $runningCommand = $this->getProcessCommand($pid);

            if ($runningCommand !== null && str_contains($runningCommand, $commandSignature)) {
                $stopped = $this->stop($pid, $timeout);

                if ($stopped) {
                    $this->deletePidFile($service);
                }

                return $stopped;
            }
        }

        // Fall back to finding by command signature
        $pids = $this->findAllByCommand($commandSignature);
        $allStopped = true;

        foreach ($pids as $foundPid) {
            if (! $this->stop($foundPid, $timeout)) {
                $allStopped = false;
            }
        }

        $this->deletePidFile($service);

        return $allStopped;
    }

    private function getPidFilePath(string $service): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $service);

        return self::PID_DIRECTORY.'/'.($safeName ?: 'unknown').'.pid';
    }

    private function ensurePidDirectory(): void
    {
        if (! $this->storage->exists(self::PID_DIRECTORY)) {
            $this->storage->makeDirectory(self::PID_DIRECTORY);
        }
    }
}
