<?php

namespace App\Console\Commands;

use App\Models\AgentBackupSetting;
use App\Support\Agent\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class AgentBackupDatabaseCommand extends Command
{
    protected $signature = 'agent:backup-database {--force-run : Run immediately and ignore schedule checks}';

    protected $description = 'Run database backup on configured schedule.';

    public function handle(AuditLogger $auditLogger): int
    {
        $settings = AgentBackupSetting::current();
        $forceRun = (bool) $this->option('force-run');
        $timezone = (string) ($settings->timezone ?: 'UTC');
        $now = CarbonImmutable::now($timezone);

        if (! $forceRun) {
            if (! $settings->is_enabled) {
                $this->line('Database backup is disabled.');

                return self::SUCCESS;
            }

            if (! $this->isDueNow($settings, $now)) {
                return self::SUCCESS;
            }

            if ($this->alreadyRanToday($settings, $now, $timezone)) {
                return self::SUCCESS;
            }
        }

        try {
            $this->configureRuntimeBackupPolicy($settings);

            $backupExit = Artisan::call('backup:run', [
                '--only-db' => true,
                '--disable-notifications' => true,
            ]);

            $backupOutput = trim(Artisan::output());

            if ($backupExit !== 0) {
                throw new \RuntimeException($backupOutput !== '' ? $backupOutput : 'backup:run returned a non-zero exit code.');
            }

            $cleanupExit = Artisan::call('backup:clean', [
                '--disable-notifications' => true,
            ]);

            $cleanupOutput = trim(Artisan::output());

            if ($cleanupExit !== 0) {
                throw new \RuntimeException($cleanupOutput !== '' ? $cleanupOutput : 'backup:clean returned a non-zero exit code.');
            }

            $settings->forceFill([
                'last_run_at' => now('UTC'),
                'last_status' => AgentBackupSetting::STATUS_SUCCEEDED,
                'last_error' => null,
            ])->save();

            $auditLogger->recordSystemAction(
                action: 'backup.run',
                targetType: 'backup_settings',
                targetId: (int) $settings->id,
                changedFields: ['last_run_at', 'last_status', 'last_error'],
                after: [
                    'last_run_at' => $settings->last_run_at?->toIso8601String(),
                    'last_status' => $settings->last_status,
                ],
            );

            $this->info('Database backup completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $settings->forceFill([
                'last_run_at' => now('UTC'),
                'last_status' => AgentBackupSetting::STATUS_FAILED,
                'last_error' => mb_substr($exception->getMessage(), 0, 5000),
            ])->save();

            $auditLogger->recordSystemAction(
                action: 'backup.run',
                targetType: 'backup_settings',
                targetId: (int) $settings->id,
                changedFields: ['last_run_at', 'last_status', 'last_error'],
                after: [
                    'last_run_at' => $settings->last_run_at?->toIso8601String(),
                    'last_status' => $settings->last_status,
                    'last_error' => $settings->last_error,
                ],
                outcome: 'failure',
                errorCode: 'BACKUP_RUN_FAILED',
                errorMessage: $exception->getMessage(),
            );

            $this->error('Database backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function isDueNow(AgentBackupSetting $settings, CarbonImmutable $now): bool
    {
        return $now->hour === (int) $settings->run_hour
            && $now->minute === (int) $settings->run_minute;
    }

    private function alreadyRanToday(AgentBackupSetting $settings, CarbonImmutable $now, string $timezone): bool
    {
        if ($settings->last_run_at === null) {
            return false;
        }

        $lastRun = CarbonImmutable::parse($settings->last_run_at)->setTimezone($timezone);

        return $lastRun->isSameDay($now) && $settings->last_status === AgentBackupSetting::STATUS_SUCCEEDED;
    }

    private function configureRuntimeBackupPolicy(AgentBackupSetting $settings): void
    {
        config()->set('backup.backup.source.files.include', []);
        config()->set('backup.backup.source.files.exclude', []);
        config()->set('backup.backup.source.databases', [config('database.default')]);
        $this->configureDumpBinaryPath();

        // Keep one daily backup for the configured retention window.
        config()->set('backup.cleanup.default_strategy.keep_all_backups_for_days', (int) $settings->retention_days);
        config()->set('backup.cleanup.default_strategy.keep_daily_backups_for_days', 0);
        config()->set('backup.cleanup.default_strategy.keep_weekly_backups_for_weeks', 0);
        config()->set('backup.cleanup.default_strategy.keep_monthly_backups_for_months', 0);
        config()->set('backup.cleanup.default_strategy.keep_yearly_backups_for_years', 0);
    }

    private function configureDumpBinaryPath(): void
    {
        $defaultConnection = (string) config('database.default');
        $driver = (string) config("database.connections.{$defaultConnection}.driver");

        if ($driver !== 'pgsql') {
            return;
        }

        $existing = config("database.connections.{$defaultConnection}.dump.dump_binary_path");
        if (is_string($existing) && trim($existing) !== '') {
            return;
        }

        if ($this->binaryExistsOnPath('pg_dump')) {
            return;
        }

        foreach ($this->pgDumpCandidateDirectories() as $directory) {
            $candidate = rtrim($directory, '/').'/pg_dump';

            if (is_executable($candidate)) {
                config()->set("database.connections.{$defaultConnection}.dump.dump_binary_path", $directory);

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function pgDumpCandidateDirectories(): array
    {
        return [
            '/opt/homebrew/opt/libpq/bin',
            '/opt/homebrew/opt/libpq@18/bin',
            '/opt/homebrew/opt/libpq@17/bin',
            '/opt/homebrew/opt/postgresql@17/bin',
            '/usr/local/opt/libpq/bin',
            '/usr/local/bin',
        ];
    }

    private function binaryExistsOnPath(string $binary): bool
    {
        $pathEnv = getenv('PATH');
        if (! is_string($pathEnv) || trim($pathEnv) === '') {
            return false;
        }

        foreach (explode(':', $pathEnv) as $directory) {
            $candidate = rtrim($directory, '/').'/'.$binary;
            if (is_executable($candidate)) {
                return true;
            }
        }

        return false;
    }
}
