<?php

declare(strict_types=1);

namespace App\Console\Commands\Tunnel;

use App\Jobs\Tunnel\TunnelRunJob;
use App\Repositories\TunnelSettingsRepository;
use App\Services\Tunnel\CloudflaredService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class TunnelRunCommand extends Command
{
    protected $signature = 'tunnel:run {--sync : Run tunnel in foreground (bypasses queue, useful when Horizon is not running)}';

    protected $description = 'Start the Cloudflare tunnel (dispatches to queue, or runs in foreground with --sync)';

    public function handle(
        TunnelSettingsRepository $repository,
        CloudflaredService $cloudflared,
    ): int {
        $settings = $repository->getSettings();
        $hasMinConfig = ! empty($settings['tunnel_uuid'])
            && ! empty($settings['hostname'])
            && ! empty($settings['origin_url'])
            && ! empty($settings['protocol']);

        if (! $hasMinConfig) {
            $this->error('Tunnel is not configured. Run tunnel setup first.');

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            return $this->runSync($repository, $cloudflared);
        }

        TunnelRunJob::dispatch();

        $this->info('Tunnel run job dispatched to the tunnel queue.');
        $this->line('Ensure Horizon is running: <fg=yellow>php artisan horizon</fg=yellow>');

        return self::SUCCESS;
    }

    private function runSync(TunnelSettingsRepository $repository, CloudflaredService $cloudflared): int
    {
        $settings = $repository->getSettings();
        $uuid = $settings['tunnel_uuid'] ?? '';
        $originUrl = $settings['origin_url'] ?? 'http://localhost:8000';
        $protocol = $settings['protocol'] ?? 'http2';
        $ipAllowlist = $settings['ip_allowlist'] ?? [];
        $hostname = $settings['hostname'] ?? null;

        $built = $cloudflared->buildRunCommand($uuid, $originUrl, $protocol, $ipAllowlist ?: null, null, $hostname);
        $command = $built['command'];

        $this->info('Starting tunnel in foreground (Ctrl+C to stop)...');
        $this->line('Origin: '.$originUrl);

        $result = Process::forever()->run($command);

        if ($result->exitCode() !== 0) {
            $this->error('Tunnel exited with code '.$result->exitCode());
            $this->line($result->errorOutput() ?: $result->output());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
