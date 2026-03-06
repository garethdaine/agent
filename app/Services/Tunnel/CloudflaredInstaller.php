<?php

declare(strict_types=1);

namespace App\Services\Tunnel;

use Illuminate\Support\Facades\Process;

class CloudflaredInstaller
{
    public function detectPlatform(): string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return 'macos';
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $aptResult = Process::run('which apt-get');
            if ($aptResult->successful()) {
                return 'linux-apt';
            }

            $yumResult = Process::run('which yum');
            if ($yumResult->successful()) {
                return 'linux-yum';
            }

            return 'linux-binary';
        }

        return 'linux-binary';
    }

    public function getInstallCommand(string $platform): string
    {
        return match ($platform) {
            'macos' => 'brew install cloudflared',
            'linux-apt' => 'sudo apt-get install -y cloudflared',
            'linux-yum' => 'sudo yum install -y cloudflared',
            default => 'curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -o /usr/local/bin/cloudflared && chmod +x /usr/local/bin/cloudflared',
        };
    }

    public function getInstallInstructions(string $platform): array
    {
        $descriptions = [
            'macos' => 'Install cloudflared using Homebrew.',
            'linux-apt' => 'Install cloudflared using apt package manager.',
            'linux-yum' => 'Install cloudflared using yum package manager.',
            'linux-binary' => 'Download the cloudflared binary directly.',
        ];

        return [
            'platform' => $platform,
            'command' => $this->getInstallCommand($platform),
            'description' => $descriptions[$platform] ?? $descriptions['linux-binary'],
            'manual_url' => 'https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/',
        ];
    }

    public function verifyInstallation(): array
    {
        $service = new CloudflaredService;
        $validation = $service->validateCredentials();

        return array_merge($validation, [
            'platform' => $this->detectPlatform(),
        ]);
    }
}
