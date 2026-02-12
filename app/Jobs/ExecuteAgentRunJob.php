<?php

namespace App\Jobs;

use App\Models\AgentJobRun;
use App\Support\Agent\CommandTemplateRenderer;
use App\Support\Agent\RunEventWriter;
use App\Support\Agent\RunStateTransitionService;
use App\Support\Agent\RuntimeValidation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\Process\Process;

class ExecuteAgentRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $backoff = 0;

    public int $timeout = 86500;

    public function __construct(public int $runId)
    {
        $this->onConnection('redis');
        $this->onQueue('agent');
    }

    public function handle(
        RuntimeValidation $runtimeValidation,
        CommandTemplateRenderer $renderer,
        RunStateTransitionService $transitions,
    ): void {
        $run = AgentJobRun::query()->with('job')->find($this->runId);

        if ($run === null || $run->job === null) {
            return;
        }

        $movedToStarting = $transitions->transition(
            (int) $run->id,
            [AgentJobRun::STATUS_QUEUED],
            AgentJobRun::STATUS_STARTING
        );

        if (! $movedToStarting) {
            return;
        }

        $run->refresh();

        $writer = new RunEventWriter($run);
        $writer->appendLifecycle([
            'type' => 'state_transition',
            'from' => AgentJobRun::STATUS_QUEUED,
            'to' => AgentJobRun::STATUS_STARTING,
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);

        $runtimeCheck = $runtimeValidation->validate($run->job);

        if (! $runtimeCheck['ok']) {
            $this->finalizeTerminal(
                $run,
                $transitions,
                AgentJobRun::STATUS_FAILED,
                [
                    'resolved_executable_path' => null,
                    'error_code' => $runtimeCheck['error_code'],
                    'error_summary' => $runtimeCheck['error_summary'],
                ]
            );

            return;
        }

        $run->resolved_executable_path = $runtimeCheck['resolved_executable_path'];
        $run->job->last_validated_executable_path = $runtimeCheck['resolved_executable_path'];
        $run->job->save();

        $tokens = $renderer->renderTokens($run->job, $run);
        $env = $this->mergedEnvironment($run);

        $process = new Process($tokens, $run->job->working_directory, $env);
        $process->setTimeout(null);

        $startedAt = CarbonImmutable::now('UTC');
        $startedAtMonotonicNs = hrtime(true);
        $stopRequestedAt = null;
        $timedOut = false;
        $terminationMode = null;

        try {
            $process->start();
        } catch (\Throwable $throwable) {
            $this->finalizeTerminal(
                $run,
                $transitions,
                AgentJobRun::STATUS_FAILED,
                [
                    'resolved_executable_path' => $run->resolved_executable_path,
                    'error_code' => 'PROCESS_START_FAILED',
                    'error_summary' => $throwable->getMessage(),
                ]
            );

            return;
        }

        $movedToRunning = $transitions->transition(
            (int) $run->id,
            [AgentJobRun::STATUS_STARTING],
            AgentJobRun::STATUS_RUNNING,
            [
                'pid' => $process->getPid(),
                'started_at' => $startedAt,
            ]
        );

        if (! $movedToRunning) {
            return;
        }

        $run->refresh();

        $writer->appendLifecycle([
            'type' => 'state_transition',
            'from' => AgentJobRun::STATUS_STARTING,
            'to' => AgentJobRun::STATUS_RUNNING,
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'pid' => $run->pid,
        ]);

        while ($process->isRunning()) {
            $stdout = $process->getIncrementalOutput();
            if ($stdout !== '') {
                $writer->appendOutput('stdout', $stdout);
            }

            $stderr = $process->getIncrementalErrorOutput();
            if ($stderr !== '') {
                $writer->appendOutput('stderr', $stderr);
            }

            $run->refresh();

            if ($run->status === AgentJobRun::STATUS_STOPPING) {
                if ($stopRequestedAt === null) {
                    $stopRequestedAt = CarbonImmutable::now('UTC');
                    $terminationMode = 'user_stop';
                    $this->signalProcess($process->getPid(), SIGTERM);
                } elseif (CarbonImmutable::now('UTC')->greaterThanOrEqualTo($stopRequestedAt->addSeconds(10))) {
                    $sent = $this->signalProcess($process->getPid(), SIGKILL);
                    if (! $sent && $process->isRunning()) {
                        $this->finalizeTerminal(
                            $run,
                            $transitions,
                            AgentJobRun::STATUS_FAILED,
                            [
                                'error_code' => 'TERMINATION_FAILED',
                                'error_summary' => 'SIGKILL could not be delivered to the target process.',
                                'metadata_json' => [
                                    ...((array) ($run->metadata_json ?? [])),
                                    'termination_mode' => 'sigkill_failed',
                                ],
                                'resolved_executable_path' => $run->resolved_executable_path,
                            ]
                        );

                        return;
                    }

                    $terminationMode = 'sigkill';
                }
            }

            $elapsedSeconds = (int) max(0, floor((hrtime(true) - $startedAtMonotonicNs) / 1_000_000_000));

            if ($elapsedSeconds >= (int) $run->job->max_runtime_seconds) {
                $timedOut = true;
                $terminationMode = 'timeout';
                $this->signalProcess($process->getPid(), SIGTERM);

                usleep(1_000_000);

                if ($process->isRunning()) {
                    $sent = $this->signalProcess($process->getPid(), SIGKILL);
                    if (! $sent && $process->isRunning()) {
                        $this->finalizeTerminal(
                            $run,
                            $transitions,
                            AgentJobRun::STATUS_FAILED,
                            [
                                'error_code' => 'TERMINATION_FAILED',
                                'error_summary' => 'SIGKILL could not be delivered after timeout escalation.',
                                'metadata_json' => [
                                    ...((array) ($run->metadata_json ?? [])),
                                    'termination_mode' => 'timeout_sigkill_failed',
                                ],
                                'resolved_executable_path' => $run->resolved_executable_path,
                            ]
                        );

                        return;
                    }
                }

                break;
            }

            usleep(250_000);
        }

        $remainingOut = $process->getIncrementalOutput();
        if ($remainingOut !== '') {
            $writer->appendOutput('stdout', $remainingOut);
        }

        $remainingErr = $process->getIncrementalErrorOutput();
        if ($remainingErr !== '') {
            $writer->appendOutput('stderr', $remainingErr);
        }

        $run->refresh();

        if (in_array($run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
            return;
        }

        $exitCode = $process->getExitCode();
        $finalStatus = AgentJobRun::STATUS_FAILED;

        if ($timedOut) {
            $finalStatus = AgentJobRun::STATUS_TIMED_OUT;
        } elseif ($run->status === AgentJobRun::STATUS_STOPPING) {
            $finalStatus = AgentJobRun::STATUS_KILLED;
        } elseif ($exitCode === 0) {
            $finalStatus = AgentJobRun::STATUS_SUCCEEDED;
        }

        $metadata = (array) ($run->metadata_json ?? []);
        if ($terminationMode !== null) {
            $metadata['termination_mode'] = $terminationMode;
        }

        $this->finalizeTerminal($run, $transitions, $finalStatus, [
            'exit_code' => $exitCode,
            'signal' => $process->getTermSignal(),
            'metadata_json' => $metadata,
            'resolved_executable_path' => $run->resolved_executable_path,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function mergedEnvironment(AgentJobRun $run): array
    {
        $env = [];

        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $env[$key] = (string) $value;
            }
        }

        foreach ((array) ($run->job->env_json ?? []) as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $env[$key] = (string) $value;
            }
        }

        return $env;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function finalizeTerminal(
        AgentJobRun $run,
        RunStateTransitionService $transitions,
        string $status,
        array $extra = []
    ): void {
        $run->refresh();

        if (in_array($run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
            return;
        }

        $finishedAt = CarbonImmutable::now('UTC');
        $durationMs = 0;

        if ($run->started_at !== null) {
            $durationMs = max(0, CarbonImmutable::parse($run->started_at, 'UTC')->diffInMilliseconds($finishedAt));
        }

        $payload = array_merge($extra, [
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
        ]);

        $transitioned = $transitions->transition(
            (int) $run->id,
            AgentJobRun::ACTIVE_STATUSES,
            $status,
            $payload
        );

        if (! $transitioned) {
            return;
        }

        $run->refresh();

        $writer = new RunEventWriter($run);
        $writer->appendLifecycle([
            'type' => 'state_transition',
            'to' => $status,
            'at' => $finishedAt->toIso8601String(),
            'exit_code' => $run->exit_code,
            'signal' => $run->signal,
        ]);

        $this->applyPathFailurePolicy($run);
    }

    private function applyPathFailurePolicy(AgentJobRun $run): void
    {
        $run->loadMissing('job');

        if ($run->job === null) {
            return;
        }

        $job = $run->job;
        $streak = (int) $job->scheduled_path_failure_streak;

        if ($run->status === AgentJobRun::STATUS_SUCCEEDED) {
            if ($streak !== 0) {
                $job->scheduled_path_failure_streak = 0;
                $job->save();
            }

            return;
        }

        if ($run->trigger_type !== AgentJobRun::TRIGGER_SCHEDULE) {
            return;
        }

        if ($run->error_code === 'RUN_PATH_NOT_FOUND') {
            $job->scheduled_path_failure_streak = min(65535, $streak + 1);

            if ((int) $job->scheduled_path_failure_streak >= 3) {
                $job->is_enabled = false;
            }

            $job->save();

            return;
        }

        if ($streak !== 0) {
            $job->scheduled_path_failure_streak = 0;
            $job->save();
        }
    }

    private function signalProcess(?int $pid, int $signal): bool
    {
        if ($pid === null || $pid <= 0 || ! function_exists('posix_kill')) {
            return false;
        }

        return @posix_kill($pid, $signal);
    }
}
