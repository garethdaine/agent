<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class BuildExecutionBackupService
{
    /**
     * Atomic lock key to prevent concurrent backup:run invocations.
     *
     * spatie/laravel-backup writes to a shared temporary directory and zip
     * file.  When multiple build ticks (e.g. the self-sustaining loop and
     * the safety-net listener) run simultaneously, concurrent backup:run
     * calls race on the same temp zip, causing ZipArchive::close() to fail
     * with "Renaming temporary file failed: No such file or directory".
     *
     * The lock ensures only one backup runs at a time; any concurrent
     * caller that cannot acquire the lock treats the in-progress backup as
     * a successful skip.
     */
    private const LOCK_KEY = 'build_execution_backup_lock';

    private const LOCK_TTL_SECONDS = 120;

    /**
     * @return array{ok:bool,attempted_at:string,message:string,output:?string,skipped:bool}
     */
    public function backupBeforeBuildStart(InterrogationSession $session): array
    {
        return $this->runBackup('build_start', $session, null, null);
    }

    /**
     * @return array{ok:bool,attempted_at:string,message:string,output:?string,skipped:bool}
     */
    public function backupBeforeTaskStart(
        InterrogationSession $session,
        InterrogationBuildTask $task,
        int $attempt,
    ): array {
        return $this->runBackup('task_start', $session, $task, $attempt);
    }

    /**
     * @return array{ok:bool,attempted_at:string,message:string,output:?string,skipped:bool}
     */
    private function runBackup(
        string $scope,
        InterrogationSession $session,
        ?InterrogationBuildTask $task,
        ?int $attempt,
    ): array {
        $attemptedAt = CarbonImmutable::now('UTC')->toIso8601String();

        if (app()->runningUnitTests()) {
            return [
                'ok' => true,
                'attempted_at' => $attemptedAt,
                'message' => 'Skipped backup command while running unit tests.',
                'output' => null,
                'skipped' => true,
            ];
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            // Another backup is already in progress — treat as a successful
            // skip rather than failing the build.  The concurrent backup
            // will produce a valid snapshot for this point in time.
            return [
                'ok' => true,
                'attempted_at' => $attemptedAt,
                'message' => sprintf(
                    'Backup skipped before %s — another backup is already in progress.',
                    $scope,
                ),
                'output' => null,
                'skipped' => true,
            ];
        }

        try {
            $exitCode = Artisan::call('agent:backup-database', ['--force-run' => true]);
            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                return [
                    'ok' => false,
                    'attempted_at' => $attemptedAt,
                    'message' => $output !== ''
                        ? mb_substr($output, 0, 2000)
                        : 'agent:backup-database returned a non-zero exit code.',
                    'output' => $output !== '' ? mb_substr($output, 0, 5000) : null,
                    'skipped' => false,
                ];
            }

            return [
                'ok' => true,
                'attempted_at' => $attemptedAt,
                'message' => sprintf(
                    'Database backup completed before %s%s.',
                    $scope,
                    $task instanceof InterrogationBuildTask
                        ? sprintf(' (task #%d, attempt %d)', (int) $task->sequence, max(1, (int) $attempt))
                        : ''
                ),
                'output' => $output !== '' ? mb_substr($output, 0, 5000) : null,
                'skipped' => false,
            ];
        } catch (\Throwable $throwable) {
            report($throwable);

            return [
                'ok' => false,
                'attempted_at' => $attemptedAt,
                'message' => mb_substr(trim((string) $throwable->getMessage()) ?: 'Database backup command failed.', 0, 2000),
                'output' => null,
                'skipped' => false,
            ];
        } finally {
            $lock->release();
        }
    }
}
