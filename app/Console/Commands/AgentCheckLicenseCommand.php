<?php

namespace App\Console\Commands;

use App\Services\Agent\LicenseService;
use Illuminate\Console\Command;

class AgentCheckLicenseCommand extends Command
{
    protected $signature = 'agent:check-license';

    protected $description = 'Validate the current license and display status';

    public function handle(LicenseService $license): int
    {
        $this->newLine();
        $this->components->info('Checking license...');

        $license->clearCache();

        $status = $license->validate();
        $fingerprint = $license->instanceFingerprint();

        $licenseKey = (string) config('agent.license.key', '');
        $maskedKey = $licenseKey !== ''
            ? substr($licenseKey, 0, 4).str_repeat('*', max(0, strlen($licenseKey) - 4))
            : '(not set)';

        $domain = parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: '(unknown)';
        $validationUrl = (string) config('agent.license.validation_url', '');

        $this->table(
            ['Property', 'Value'],
            [
                ['License Key', $maskedKey],
                ['Domain', $domain],
                ['Fingerprint', $fingerprint],
                ['Validation URL', $validationUrl],
                ['Plan', $status->plan ?: '—'],
                ['Expires', $status->expiresAt ?: '—'],
                ['Status', $status->valid ? '<fg=green>VALID</>' : '<fg=red>INVALID</>'],
            ]
        );

        if ($status->valid) {
            $this->components->info("License is valid ({$status->plan}).");

            return self::SUCCESS;
        }

        $this->components->error($status->error ?? 'License is not valid.');

        return self::FAILURE;
    }
}
