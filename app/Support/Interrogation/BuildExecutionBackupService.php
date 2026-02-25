<?php

namespace App\Support\Interrogation;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

class BuildExecutionBackupService
{
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
        }
    }
}
