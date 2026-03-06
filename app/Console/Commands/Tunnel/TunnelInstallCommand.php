<?php

declare(strict_types=1);

namespace App\Console\Commands\Tunnel;

use App\Services\Tunnel\CloudflaredInstaller;
use App\Services\Tunnel\CloudflaredService;
use Illuminate\Console\Command;

class TunnelInstallCommand extends Command
{
    protected $signature = 'tunnel:install';

    protected $description = 'Check cloudflared installation and provide platform-appropriate install instructions';

    public function handle(CloudflaredInstaller $installer, CloudflaredService $service): int
    {
        $platform = $installer->detectPlatform();
        $instructions = $installer->getInstallInstructions($platform);

        $this->info('Platform: '.$instructions['platform']);
        $this->info('Install Command: '.$instructions['command']);
        $this->info('Description: '.$instructions['description']);
        $this->info('Manual URL: '.$instructions['manual_url']);

        $this->newLine();
        $this->info('Running validation checks...');
        $this->newLine();

        $validation = $service->validateCredentials();

        $this->table(
            ['Check', 'Status'],
            [
                ['Binary installed', $validation['binary'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'],
                ['Credentials (cert.pem)', $validation['credentials'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'],
                ['Functional (tunnel list)', $validation['functional'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'],
            ]
        );

        if ($validation['version']) {
            $this->info('Installed version: '.$validation['version']);
        }

        $versionCompat = $service->isVersionCompatible();
        if ($versionCompat['warning']) {
            $this->warn($versionCompat['warning']);
        }

        return self::SUCCESS;
    }
}
