<?php

namespace App\Jobs;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Models\AgentJobRun;
use App\Support\Agent\CommandTemplateRenderer;
use App\Support\Agent\Duration;
use App\Support\Agent\RunEventWriter;
use App\Support\Agent\RunStateTransitionService;
use App\Support\Agent\RuntimeValidation;
use App\Support\Agent\UsageLimitState;
use App\Support\Compliance\DTOs\PolicyEvaluationResult;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ExecuteAgentRunJob implements ShouldQueue
{
    use Queueable;

    private ?UsageLimitState $usageLimitState = null;

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
        UsageLimitState $usageLimitState,
        OrchestrationPolicyServiceContract $policyService,
    ): void {
        $this->usageLimitState = $usageLimitState;

        $run = AgentJobRun::query()->with('job')->find($this->runId);

        if ($run === null || $run->job === null) {
            return;
        }

        try {
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

            // Policy evaluation after state transition, before runtime validation
            if ($policyService->isEnabled()) {
                $preRunResult = $policyService->evaluatePreRun($run, $this->extractRunContext($run));
                $this->recordComplianceMetadata($run, $preRunResult);

                if ($preRunResult->isBlocked()) {
                    $this->finalizeTerminal(
                        $run,
                        $transitions,
                        AgentJobRun::STATUS_FAILED,
                        [
                            'error_code' => 'COMPLIANCE_BLOCKED',
                            'error_summary' => 'Pre-run compliance check failed: '.($preRunResult->gates[0]?->remediation ?? 'Unknown'),
                            'metadata_json' => [
                                ...((array) ($run->metadata_json ?? [])),
                                'compliance_block_reason' => $preRunResult->gates[0]?->reasonCode,
                            ],
                        ]
                    );

                    return;
                }

                if ($preRunResult->status === 'advisory') {
                    Log::info('ExecuteAgentRunJob: Advisory compliance warning', [
                        'run_id' => $run->id,
                        'gates' => array_map(fn ($g) => [
                            'gate' => $g->gate,
                            'status' => $g->status,
                            'reason' => $g->reasonCode,
                        ], $preRunResult->gates),
                    ]);
                }
            }

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
                    'metadata_json' => [
                        ...((array) ($run->metadata_json ?? [])),
                        'launch_fingerprint' => [
                            'executable' => (string) ($run->resolved_executable_path ?? ''),
                            'executable_token' => (string) ($tokens[0] ?? ''),
                            'task_markdown_path' => (string) ($run->job->task_markdown_path ?? ''),
                        ],
                    ],
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

            $heartbeatIntervalSeconds = max(0, (int) config('agent.run_output_heartbeat_seconds', 5));
            $nextHeartbeatAt = $heartbeatIntervalSeconds > 0
                ? CarbonImmutable::now('UTC')->addSeconds($heartbeatIntervalSeconds)
                : null;

            while ($process->isRunning()) {
                $stdout = $process->getIncrementalOutput();
                if ($stdout !== '') {
                    $writer->appendOutput('stdout', $stdout);
                }

                $stderr = $process->getIncrementalErrorOutput();
                if ($stderr !== '') {
                    $writer->appendOutput('stderr', $stderr);
                }

                $emittedOutput = $stdout !== '' || $stderr !== '';

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

                if (! $emittedOutput && $nextHeartbeatAt instanceof CarbonImmutable) {
                    $now = CarbonImmutable::now('UTC');
                    if ($now->greaterThanOrEqualTo($nextHeartbeatAt)) {
                        $writer->appendLifecycle([
                            'type' => 'heartbeat',
                            'at' => $now->toIso8601String(),
                            'elapsed_seconds' => $elapsedSeconds,
                            'status' => (string) ($run->status ?? AgentJobRun::STATUS_RUNNING),
                        ]);
                        $nextHeartbeatAt = $now->addSeconds($heartbeatIntervalSeconds);
                    }
                }

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
            $metadata = (array) ($run->metadata_json ?? []);
            $permissionBlockerDetected = ($metadata['permission_blocker_detected'] ?? false) === true;
            $finalStatus = AgentJobRun::STATUS_FAILED;
            $errorCode = null;
            $errorSummary = null;

            if ($timedOut) {
                $finalStatus = AgentJobRun::STATUS_TIMED_OUT;
            } elseif ($run->status === AgentJobRun::STATUS_STOPPING) {
                $finalStatus = AgentJobRun::STATUS_KILLED;
            } elseif ($exitCode === 0 && ! $permissionBlockerDetected) {
                $finalStatus = AgentJobRun::STATUS_SUCCEEDED;
            }

            // Completion gate evaluation before finalizing as successful
            if ($finalStatus === AgentJobRun::STATUS_SUCCEEDED && $policyService->isEnabled()) {
                $completionResult = $policyService->evaluateCompletion($run);
                if (! $completionResult->canComplete) {
                    $this->finalizeTerminal($run, $transitions, AgentJobRun::STATUS_FAILED, [
                        'exit_code' => $exitCode,
                        'signal' => $process->getTermSignal(),
                        'error_code' => 'COMPLIANCE_BLOCKED',
                        'error_summary' => $completionResult->remediation ?? 'Completion gate failed',
                        'metadata_json' => [
                            ...$metadata,
                            'compliance_block_reason' => $completionResult->blockReason,
                        ],
                        'resolved_executable_path' => $run->resolved_executable_path,
                    ]);

                    return;
                }
            }

            if ($finalStatus === AgentJobRun::STATUS_FAILED && $permissionBlockerDetected) {
                $errorCode = 'PERMISSION_REQUIRED';
                $errorSummary = $this->resolvePermissionBlockerSummary($metadata);
            }

            if ($terminationMode !== null) {
                $metadata['termination_mode'] = $terminationMode;
            }

            $payload = [
                'exit_code' => $exitCode,
                'signal' => $process->getTermSignal(),
                'metadata_json' => $metadata,
                'resolved_executable_path' => $run->resolved_executable_path,
            ];

            if (is_string($errorCode) && $errorCode !== '') {
                $payload['error_code'] = $errorCode;
            }

            if (is_string($errorSummary) && trim($errorSummary) !== '') {
                $payload['error_summary'] = $errorSummary;
            }

            $this->finalizeTerminal($run, $transitions, $finalStatus, $payload);
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->failRunSafely($run, $transitions, $throwable);
        }
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
        $durationMs = Duration::millisecondsBetween($run->started_at, $finishedAt);

        $baseMetadata = (array) ($run->metadata_json ?? []);
        $incomingMetadata = (array) ($extra['metadata_json'] ?? []);
        $mergedMetadata = array_merge($baseMetadata, $incomingMetadata);

        if ($status === AgentJobRun::STATUS_FAILED && ($mergedMetadata['rate_limit_detected'] ?? false) === true) {
            $holdUntil = $this->resolveRateLimitHoldUntil($mergedMetadata, $finishedAt);
            $mergedMetadata['rate_limit_hold_until'] = $holdUntil->toIso8601String();

            if (! isset($extra['error_code']) || ! is_string($extra['error_code']) || trim((string) $extra['error_code']) === '') {
                $extra['error_code'] = 'RATE_LIMITED';
            }

            if (! isset($extra['error_summary']) || ! is_string($extra['error_summary']) || trim((string) $extra['error_summary']) === '') {
                $extra['error_summary'] = 'Runner hit an upstream usage/rate limit.';
            }
        }

        if ($status === AgentJobRun::STATUS_FAILED && ($mergedMetadata['permission_blocker_detected'] ?? false) === true) {
            if (! isset($extra['error_code']) || ! is_string($extra['error_code']) || trim((string) $extra['error_code']) === '') {
                $extra['error_code'] = 'PERMISSION_REQUIRED';
            }

            if (! isset($extra['error_summary']) || ! is_string($extra['error_summary']) || trim((string) $extra['error_summary']) === '') {
                $extra['error_summary'] = $this->resolvePermissionBlockerSummary($mergedMetadata);
            }
        }

        if (($mergedMetadata['approval_required'] ?? false) === true) {
            $mergedMetadata['approval_required'] = false;
            $mergedMetadata['approval_resolved_at'] = $finishedAt->toIso8601String();
            $mergedMetadata['approval_resolution'] = $status;
        }

        if (($mergedMetadata['permission_blocker_detected'] ?? false) === true) {
            $mergedMetadata['permission_blocker_resolved_at'] = $finishedAt->toIso8601String();
            $mergedMetadata['permission_blocker_resolution'] = $status;
        }

        if (($mergedMetadata['clarification_required'] ?? false) === true) {
            $mergedMetadata['clarification_resolved_at'] = $finishedAt->toIso8601String();
            $mergedMetadata['clarification_resolution'] = $status;
        }

        $extra['metadata_json'] = $mergedMetadata;

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

        $this->applyUsageLimitPolicy($run, $status);

        try {
            $writer = new RunEventWriter($run);
            $writer->appendLifecycle([
                'type' => 'state_transition',
                'to' => $status,
                'at' => $finishedAt->toIso8601String(),
                'exit_code' => $run->exit_code,
                'signal' => $run->signal,
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        $this->applyPathFailurePolicy($run);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveRateLimitHoldUntil(array $metadata, CarbonImmutable $fallbackFrom): CarbonImmutable
    {
        $defaultMinutes = max(1, (int) config('agent.rate_limit_default_hold_minutes', 15));

        foreach (['rate_limit_hold_until', 'rate_limit_reset_at'] as $key) {
            $candidate = $metadata[$key] ?? null;

            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            try {
                $parsed = CarbonImmutable::parse($candidate, 'UTC');
                if ($parsed->greaterThan($fallbackFrom)) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // Fall through to default hold.
            }
        }

        return $fallbackFrom->addMinutes($defaultMinutes);
    }

    private function applyUsageLimitPolicy(AgentJobRun $run, string $status): void
    {
        if ($this->usageLimitState === null || $run->job === null) {
            return;
        }

        if ($status === AgentJobRun::STATUS_SUCCEEDED) {
            $this->usageLimitState->clearHold((int) $run->job->id);

            return;
        }

        if ($status !== AgentJobRun::STATUS_FAILED) {
            return;
        }

        $metadata = (array) ($run->metadata_json ?? []);
        if (($metadata['rate_limit_detected'] ?? false) !== true) {
            return;
        }

        $holdUntil = $this->resolveRateLimitHoldUntil($metadata, CarbonImmutable::now('UTC'));

        $this->usageLimitState->upsertHold((int) $run->job->id, $holdUntil, [
            'source_run_id' => (int) $run->id,
            'detected_at' => $metadata['rate_limit_detected_at'] ?? null,
            'reset_at' => $metadata['rate_limit_reset_at'] ?? null,
            'excerpt' => $metadata['rate_limit_excerpt'] ?? null,
        ]);
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

    private function failRunSafely(AgentJobRun $run, RunStateTransitionService $transitions, \Throwable $throwable): void
    {
        $run->refresh();

        if (in_array($run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
            return;
        }

        $finishedAt = CarbonImmutable::now('UTC');
        $metadata = (array) ($run->metadata_json ?? []);
        $metadata['termination_mode'] = 'runner_exception';
        if (($metadata['approval_required'] ?? false) === true) {
            $metadata['approval_required'] = false;
            $metadata['approval_resolved_at'] = $finishedAt->toIso8601String();
            $metadata['approval_resolution'] = 'runner_exception';
        }
        if (($metadata['permission_blocker_detected'] ?? false) === true) {
            $metadata['permission_blocker_resolved_at'] = $finishedAt->toIso8601String();
            $metadata['permission_blocker_resolution'] = 'runner_exception';
        }
        if (($metadata['clarification_required'] ?? false) === true) {
            $metadata['clarification_resolved_at'] = $finishedAt->toIso8601String();
            $metadata['clarification_resolution'] = 'runner_exception';
        }

        $transitions->transition(
            (int) $run->id,
            AgentJobRun::ACTIVE_STATUSES,
            AgentJobRun::STATUS_FAILED,
            [
                'finished_at' => $finishedAt,
                'duration_ms' => Duration::millisecondsBetween($run->started_at, $finishedAt),
                'error_code' => 'RUNNER_EXCEPTION',
                'error_summary' => substr($throwable->getMessage(), 0, 500),
                'metadata_json' => $metadata,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolvePermissionBlockerSummary(array $metadata): string
    {
        $excerpt = trim((string) ($metadata['permission_blocker_excerpt'] ?? ''));

        if ($excerpt !== '') {
            return substr($excerpt, 0, 500);
        }

        return 'Runner reported missing write permissions and could not proceed.';
    }

    /**
     * Extract context for complexity classification.
     *
     * @return array<string, mixed>
     */
    private function extractRunContext(AgentJobRun $run): array
    {
        $metadata = (array) ($run->metadata_json ?? []);

        return [
            'task_category' => $metadata['task_category'] ?? 'custom',
            'file_count' => $metadata['estimated_file_count'] ?? 0,
            'loc_count' => $metadata['estimated_loc_count'] ?? 0,
            'directory_count' => $metadata['estimated_directory_count'] ?? 0,
        ];
    }

    /**
     * Record compliance metadata in run's metadata_json.
     */
    private function recordComplianceMetadata(AgentJobRun $run, PolicyEvaluationResult $result): void
    {
        $metadata = (array) ($run->metadata_json ?? []);
        $run->metadata_json = array_merge($metadata, $result->metadataPatch);
        $run->save();
    }
}
