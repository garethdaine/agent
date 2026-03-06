<?php

declare(strict_types=1);

namespace App\Console\Commands\Tunnel;

use App\Services\Tunnel\TunnelHealthMonitor;
use Illuminate\Console\Command;

class TunnelStatusCommand extends Command
{
    protected $signature = 'tunnel:status';

    protected $description = 'Display tunnel health and status';

    public function handle(TunnelHealthMonitor $monitor): int
    {
        $health = $monitor->getHealth();

        $uptimeFormatted = $this->formatUptime($health['metrics']['uptime_seconds'] ?? 0);

        $this->table(
            ['Field', 'Value'],
            [
                ['Status', $health['status']],
                ['Hostname', $health['hostname'] ?: '(not set)'],
                ['Process alive', $health['process']['alive'] ? 'Yes' : 'No'],
                ['Process PID', $health['process']['pid'] ?? 'N/A'],
                ['Connections', $health['metrics']['connections'] ?? 0],
                ['Uptime', $uptimeFormatted],
                ['Error count', $health['metrics']['error_count'] ?? 0],
                ['Version', $health['version']['installed_version'] ?? 'Unknown'],
                ['Version warning', $health['version']['warning'] ?? 'None'],
            ]
        );

        return self::SUCCESS;
    }

    private function formatUptime(int $seconds): string
    {
        if ($seconds === 0) {
            return 'N/A';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}
