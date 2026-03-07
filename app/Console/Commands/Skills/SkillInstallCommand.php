<?php

declare(strict_types=1);

namespace App\Console\Commands\Skills;

use App\Services\Skills\SkillInstaller;
use Illuminate\Console\Command;

class SkillInstallCommand extends Command
{
    protected $signature = 'skill:install
        {path-or-url : Path to .skill file or URL to download}
        {--team= : Team ID}
        {--global : Install as global skill}';

    protected $description = 'Install a skill from a file path or URL';

    public function handle(SkillInstaller $installer): int
    {
        $pathOrUrl = $this->argument('path-or-url');
        $teamId = (int) ($this->option('team') ?? 0);
        $isGlobal = (bool) $this->option('global');
        $userId = null;

        $isUrl = str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://');

        if ($isUrl) {
            $result = $installer->installFromUrl($pathOrUrl, $teamId, $userId);
        } else {
            $result = $installer->installFromFile($pathOrUrl, $teamId, $userId, $isGlobal);
        }

        if ($result->requiresAdminConfirmation) {
            $riskScore = $result->validationResult?->riskScore ?? 0;

            $this->displayValidationResults($result->validationResult);

            if (! $this->confirm(sprintf('Risk score is %.3f. Do you want to proceed with installation?', $riskScore))) {
                $this->error('Installation aborted by user.');

                return self::FAILURE;
            }

            // Re-install with confirmation (the service returned early, so we retry)
            if ($isUrl) {
                $result = $installer->installFromUrl($pathOrUrl, $teamId, $userId);
            } else {
                $result = $installer->installFromFile($pathOrUrl, $teamId, $userId, $isGlobal);
            }
        }

        if ($result->blocked) {
            $this->displayValidationResults($result->validationResult);
            $this->error('Installation blocked: risk score exceeds maximum threshold.');

            return self::FAILURE;
        }

        if (! $result->success) {
            foreach ($result->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->displayValidationResults($result->validationResult);

        foreach ($result->warnings as $warning) {
            $this->warn("Warning: {$warning}");
        }

        $this->info("Skill installed successfully. (ID: {$result->skillId})");

        return self::SUCCESS;
    }

    private function displayValidationResults($validationResult): void
    {
        if ($validationResult === null) {
            return;
        }

        $rows = [];
        foreach ($validationResult->stages as $name => $stage) {
            $rows[] = [
                $name,
                $stage->passed ? 'PASS' : 'FAIL',
                $stage->checks,
                count($stage->failures),
                count($stage->warnings),
            ];
        }

        $this->table(
            ['Stage', 'Result', 'Checks', 'Failures', 'Warnings'],
            $rows,
        );

        $this->info(sprintf('Risk Score: %.3f', $validationResult->riskScore));
    }
}
