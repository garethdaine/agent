<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Models\AgentJobRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class PreRunDatabaseBackup
{
    /**
     * @return array{ok: bool, attempted_at: string, message: string, output: ?string, skipped: bool, duration_ms: int}
     */
    public function backup(AgentJobRun $run): array
    {
        $attemptedAt = CarbonImmutable::now('UTC');

        if (app()->runningUnitTests()) {
            return [
                'ok' => true,
                'attempted_at' => $attemptedAt->toIso8601String(),
                'message' => 'Skipped backup while running unit tests.',
                'output' => null,
                'skipped' => true,
                'duration_ms' => 0,
            ];
        }

        $startNs = hrtime(true);

        try {
            $exitCode = Artisan::call('agent:backup-database', ['--force-run' => true]);
            $output = trim(Artisan::output());
            $durationMs = (int) floor((hrtime(true) - $startNs) / 1_000_000);

            if ($exitCode !== 0) {
                Log::warning('Pre-run database backup failed', [
                    'run_id' => $run->id,
                    'job_id' => $run->agent_job_id,
                    'exit_code' => $exitCode,
                    'output' => mb_substr($output, 0, 2000),
                ]);

                return [
                    'ok' => false,
                    'attempted_at' => $attemptedAt->toIso8601String(),
                    'message' => $output !== ''
                        ? mb_substr($output, 0, 2000)
                        : 'agent:backup-database returned a non-zero exit code.',
                    'output' => $output !== '' ? mb_substr($output, 0, 5000) : null,
                    'skipped' => false,
                    'duration_ms' => $durationMs,
                ];
            }

            return [
                'ok' => true,
                'attempted_at' => $attemptedAt->toIso8601String(),
                'message' => sprintf(
                    'Database backup completed before run #%d (job #%d).',
                    (int) $run->id,
                    (int) $run->agent_job_id
                ),
                'output' => $output !== '' ? mb_substr($output, 0, 5000) : null,
                'skipped' => false,
                'duration_ms' => $durationMs,
            ];
        } catch (\Throwable $throwable) {
            $durationMs = (int) floor((hrtime(true) - $startNs) / 1_000_000);

            report($throwable);

            Log::warning('Pre-run database backup threw exception', [
                'run_id' => $run->id,
                'job_id' => $run->agent_job_id,
                'error' => $throwable->getMessage(),
            ]);

            return [
                'ok' => false,
                'attempted_at' => $attemptedAt->toIso8601String(),
                'message' => mb_substr(
                    trim($throwable->getMessage()) ?: 'Database backup command failed.',
                    0,
                    2000
                ),
                'output' => null,
                'skipped' => false,
                'duration_ms' => $durationMs,
            ];
        }
    }
}
