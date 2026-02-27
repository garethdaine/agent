<?php

namespace App\Support\Agent;

use App\Jobs\ExecuteInterrogationBuildJob;
use App\Models\AgentJobRun;
use Carbon\CarbonImmutable;

class ReconcileActiveRunsService
{
    public function __construct(
        private RunStateTransitionService $transitions,
        private CommandTemplateRenderer $renderer,
    ) {}

    /**
     * @return array{reconciled:int,failed:int,killed:int,mismatch:int}
     */
    public function reconcile(string $phase = 'tick'): array
    {
        $runs = AgentJobRun::query()
            ->with('job')
            ->whereIn('status', [
                AgentJobRun::STATUS_STARTING,
                AgentJobRun::STATUS_RUNNING,
                AgentJobRun::STATUS_STOPPING,
            ])
            ->orderBy('id')
            ->get();

        $summary = [
            'reconciled' => 0,
            'failed' => 0,
            'killed' => 0,
            'mismatch' => 0,
        ];

        foreach ($runs as $run) {
            $reconcileReason = null;
            $errorCode = null;
            $errorSummary = null;

            $targetStatus = $run->status === AgentJobRun::STATUS_STOPPING
                ? AgentJobRun::STATUS_KILLED
                : AgentJobRun::STATUS_FAILED;

            if ($run->pid === null || (int) $run->pid <= 0) {
                $reconcileReason = 'pid_missing';
                $errorCode = $targetStatus === AgentJobRun::STATUS_FAILED ? 'PID_MISSING' : null;
                $errorSummary = 'Active run has no PID during reconciliation.';
            } elseif (! $this->processExists((int) $run->pid)) {
                $reconcileReason = 'process_not_found';
                $errorCode = $targetStatus === AgentJobRun::STATUS_FAILED ? 'PROCESS_NOT_FOUND' : null;
                $errorSummary = 'Run PID no longer exists during reconciliation.';
            } else {
                $commandLine = $this->readProcessCommandLine((int) $run->pid);

                if ($commandLine !== null && ! $this->matchesFingerprint($run, $commandLine)) {
                    $reconcileReason = 'pid_reused_or_mismatch';
                    $errorCode = 'PID_MISMATCH';
                    $errorSummary = 'Process fingerprint does not match the expected command.';
                    $targetStatus = AgentJobRun::STATUS_FAILED;
                    $summary['mismatch']++;
                }
            }

            if ($reconcileReason === null) {
                continue;
            }

            $metadata = (array) ($run->metadata_json ?? []);
            $metadata['reconcile_reason'] = $reconcileReason;

            if ($reconcileReason === 'pid_missing') {
                $metadata['pid_not_found'] = true;

                if ($targetStatus === AgentJobRun::STATUS_KILLED) {
                    $metadata['termination_mode'] = 'pid_missing';
                }
            }

            $finishedAt = CarbonImmutable::now('UTC');
            $durationMs = Duration::millisecondsBetween($run->started_at, $finishedAt);
            if (($metadata['approval_required'] ?? false) === true) {
                $metadata['approval_required'] = false;
                $metadata['approval_resolved_at'] = $finishedAt->toIso8601String();
                $metadata['approval_resolution'] = $targetStatus;
            }

            $transitioned = $this->transitions->transition(
                (int) $run->id,
                AgentJobRun::ACTIVE_STATUSES,
                $targetStatus,
                [
                    'finished_at' => $finishedAt,
                    'duration_ms' => $durationMs,
                    'error_code' => $errorCode,
                    'error_summary' => $errorSummary,
                    'metadata_json' => $metadata,
                ]
            );

            if (! $transitioned) {
                continue;
            }

            $run->refresh();
            $writer = new RunEventWriter($run);
            $writer->appendLifecycle([
                'type' => 'system_notice',
                'notice' => 'reconciled_terminal',
                'phase' => $phase,
                'reason' => $reconcileReason,
                'to' => $targetStatus,
                'at' => $finishedAt->toIso8601String(),
            ]);
            $this->dispatchInterrogationBuildFollowUp($run, $phase, $reconcileReason);

            $summary['reconciled']++;

            if ($targetStatus === AgentJobRun::STATUS_KILLED) {
                $summary['killed']++;
            }

            if ($targetStatus === AgentJobRun::STATUS_FAILED) {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function dispatchInterrogationBuildFollowUp(
        AgentJobRun $run,
        string $phase,
        string $reconcileReason,
    ): void {
        $metadata = is_array($run->metadata_json) ? $run->metadata_json : [];

        if (($metadata['source'] ?? null) !== 'interrogation_build') {
            return;
        }

        $sessionId = (int) ($metadata['interrogation_session_id'] ?? 0);
        if ($sessionId <= 0) {
            return;
        }

        try {
            ExecuteInterrogationBuildJob::dispatch($sessionId);
        } catch (\Throwable $throwable) {
            report($throwable);

            $writer = new RunEventWriter($run);
            $writer->appendLifecycle([
                'type' => 'system_notice',
                'notice' => 'build_reconcile_dispatch_failed',
                'phase' => $phase,
                'reason' => $reconcileReason,
                'session_id' => $sessionId,
                'error' => mb_substr(trim((string) $throwable->getMessage()), 0, 300),
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);
        }
    }

    private function processExists(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        $output = [];
        $code = 1;
        exec(sprintf('ps -p %d -o pid=', $pid), $output, $code);

        return $code === 0 && trim(implode("\n", $output)) !== '';
    }

    private function readProcessCommandLine(int $pid): ?string
    {
        $procPath = sprintf('/proc/%d/cmdline', $pid);

        if (is_readable($procPath)) {
            $raw = @file_get_contents($procPath);

            if (is_string($raw) && $raw !== '') {
                return str_replace("\0", ' ', trim($raw));
            }
        }

        $output = [];
        $code = 1;
        exec(sprintf('ps -p %d -o command=', $pid), $output, $code);

        if ($code !== 0) {
            return null;
        }

        $commandLine = trim(implode("\n", $output));

        return $commandLine === '' ? null : $commandLine;
    }

    private function matchesFingerprint(AgentJobRun $run, string $commandLine): bool
    {
        if ($run->job === null) {
            return false;
        }

        $metadata = (array) ($run->metadata_json ?? []);
        $launchFingerprint = $metadata['launch_fingerprint'] ?? null;
        $hasLaunchFingerprint = is_array($launchFingerprint);
        $expectedTaskPath = null;
        $executableCandidates = [];

        if ($hasLaunchFingerprint) {
            $snapshotExecutable = $launchFingerprint['executable'] ?? null;
            $snapshotExecutableToken = $launchFingerprint['executable_token'] ?? null;
            $snapshotTaskPath = $launchFingerprint['task_markdown_path'] ?? null;

            if (is_string($snapshotExecutable) && trim($snapshotExecutable) !== '') {
                $executableCandidates[] = trim($snapshotExecutable);
            }

            if (is_string($snapshotExecutableToken) && trim($snapshotExecutableToken) !== '') {
                $executableCandidates[] = trim($snapshotExecutableToken);
            }

            if (is_string($snapshotTaskPath) && trim($snapshotTaskPath) !== '') {
                $expectedTaskPath = trim($snapshotTaskPath);
            }
        }

        $tokens = $this->renderer->renderTokens($run->job, $run);

        if ($tokens === []) {
            return false;
        }

        $configuredRunnerExecutable = (config('agent.runner_executables', []))[$run->job->runner_type] ?? null;
        if (is_string($configuredRunnerExecutable) && trim($configuredRunnerExecutable) !== '') {
            $executableCandidates[] = trim($configuredRunnerExecutable);
        }

        $expectedExecutable = $run->resolved_executable_path;
        if (is_string($expectedExecutable) && trim($expectedExecutable) !== '') {
            $executableCandidates[] = trim($expectedExecutable);
        }

        $renderedExecutable = (string) ($tokens[0] ?? '');
        if (trim($renderedExecutable) !== '') {
            $executableCandidates[] = trim($renderedExecutable);
        }

        $resolvedRenderedExecutable = realpath($tokens[0]);
        if (is_string($resolvedRenderedExecutable) && $resolvedRenderedExecutable !== '') {
            $executableCandidates[] = $resolvedRenderedExecutable;
        }

        $executableCandidates = array_values(array_unique(array_filter($executableCandidates, static fn ($value) => is_string($value) && $value !== '')));

        if ($hasLaunchFingerprint) {
            if (is_string($expectedTaskPath) && $expectedTaskPath !== '' && str_contains($commandLine, $expectedTaskPath)) {
                return true;
            }
        }

        foreach ($executableCandidates as $candidate) {
            if (str_contains($commandLine, $candidate)) {
                return true;
            }
        }

        if ($hasLaunchFingerprint) {
            return false;
        }

        if (in_array('{{task_markdown_path}}', config('agent.allowed_placeholders', []), true)
            && str_contains($run->job->command_template, '{{task_markdown_path}}')
            && ! str_contains($commandLine, $run->job->task_markdown_path)) {
            return false;
        }

        return true;
    }
}
