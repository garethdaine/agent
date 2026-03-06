<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use Illuminate\Support\Facades\Process;

class TunnelSyncService
{
    private const PID_FILE = 'tunnel-sync.pid';

    public function spawnSyncProcess(): array
    {
        $pidFile = $this->getPidFilePath();
        if (file_exists($pidFile)) {
            $existingPid = (int) trim((string) file_get_contents($pidFile));
            if ($existingPid > 0 && $this->isProcessRunning($existingPid)) {
                return [
                    'success' => false,
                    'error' => 'Tunnel is already running (PID '.$existingPid.').',
                ];
            }
            @unlink($pidFile);
        }

        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $artisan = base_path('artisan');
        $logPath = storage_path('logs/tunnel.log');

        $cmd = sprintf(
            'nohup %s %s tunnel:run --sync >> %s 2>&1 & echo $!',
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg($logPath)
        );

        $result = Process::run($cmd);
        $output = trim($result->output() ?: '');
        $pid = (int) $output;

        if ($pid <= 0) {
            return [
                'success' => false,
                'error' => 'Failed to start tunnel process.',
            ];
        }

        $dir = dirname($pidFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($pidFile, (string) $pid);

        return [
            'success' => true,
            'pid' => $pid,
        ];
    }

    public function killSyncProcess(): bool
    {
        $pidFile = $this->getPidFilePath();
        if (! file_exists($pidFile)) {
            return true;
        }

        $pid = (int) trim((string) file_get_contents($pidFile));
        @unlink($pidFile);

        if ($pid <= 0) {
            return true;
        }

        if (! $this->isProcessRunning($pid)) {
            return true;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec("taskkill /PID {$pid} /F");
        } else {
            posix_kill($pid, SIGTERM);
            $waited = 0;
            while ($waited < 5 && $this->isProcessRunning($pid)) {
                usleep(200_000);
                $waited++;
            }
            if ($this->isProcessRunning($pid)) {
                posix_kill($pid, SIGKILL);
            }
        }

        return true;
    }

    public function hasSyncProcess(): bool
    {
        $pidFile = $this->getPidFilePath();
        if (! file_exists($pidFile)) {
            return false;
        }
        $pid = (int) trim((string) file_get_contents($pidFile));

        return $pid > 0 && $this->isProcessRunning($pid);
    }

    private function getPidFilePath(): string
    {
        return storage_path('app/'.self::PID_FILE);
    }

    private function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);
            $line = implode(' ', $output);

            return str_contains($line, (string) $pid);
        }

        return posix_kill($pid, 0);
    }
}
