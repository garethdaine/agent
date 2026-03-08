<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\AdversarialReviewerService;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use App\Support\Interrogation\ExportService;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\PlanPayloadGuard;
use App\Support\Interrogation\PlanPayloadNormalizer;
use App\Support\Interrogation\SessionStateTransitionService;
use App\Support\Interrogation\SystemPromptResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\Process\Process;

class ExecuteInterrogationPlanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(public int $sessionId, public ?string $revisionPrompt = null)
    {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(
        AdapterFactory $adapterFactory,
        SessionStateTransitionService $transitions,
        SystemPromptResolver $promptResolver,
        ExportService $exportService,
        PlanPayloadNormalizer $planPayloadNormalizer,
        PlanPayloadGuard $planPayloadGuard,
    ): void {
        $session = InterrogationSession::query()->find($this->sessionId);
        $isRevisionRequest = trim((string) $this->revisionPrompt) !== '';
        $baselinePlan = [];

        if ($session === null) {
            return;
        }

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true) && $session->status !== InterrogationSession::STATUS_COMPLETED) {
            if (! $isRevisionRequest || $session->status !== InterrogationSession::STATUS_FAILED) { // @phpstan-ignore notIdentical.alwaysFalse
                return;
            }
        }

        $writer = new InterrogationEventWriter($session);
        if ($isRevisionRequest && is_array($session->plan_json) && $session->plan_json !== []) {
            $baselinePlan = $planPayloadNormalizer->normalize($session->plan_json);
        }

        try {
            if ((int) $session->phase !== InterrogationSession::PHASE_PLANNING || $session->status !== InterrogationSession::STATUS_PLANNING) {
                $transitions->transition(
                    (int) $session->id,
                    [InterrogationSession::STATUS_SUMMARIZING, InterrogationSession::STATUS_INTERROGATING, InterrogationSession::STATUS_PAUSED],
                    InterrogationSession::STATUS_PLANNING,
                    ['phase' => InterrogationSession::PHASE_PLANNING],
                );

                $session->refresh();
            }

            $adapter = $adapterFactory->make((string) $session->runner_type, $session->model);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'planning');

            if ($isRevisionRequest) {
                $this->markRevisionState($session, 'running');
            } else {
                $this->markGenerationState($session, 'running');
            }

            $planningPrompt = $this->buildPlanningPrompt($session, $isRevisionRequest, $baselinePlan);

            $planningSession = clone $session;
            $planningSession->cli_session_id = null;

            $process = $this->runPlanProcess(
                $adapter,
                $planningSession,
                $planningPrompt,
                $systemPrompt,
                $isRevisionRequest,
            );

            $plan = null;
            if ($process->getExitCode() === 0) {
                $plan = $adapter->parsePlanResponse((string) $process->getOutput());
            }

            $shouldRetryWithoutResume = is_string($planningSession->cli_session_id) // @phpstan-ignore booleanAnd.leftAlwaysFalse, booleanAnd.alwaysFalse
                && trim($planningSession->cli_session_id) !== ''
                && ($process->getExitCode() !== 0 || $plan === null);

            if ($shouldRetryWithoutResume) { // @phpstan-ignore if.alwaysFalse
                $freshSession = clone $session;
                $freshSession->cli_session_id = null;
                $fallbackProcess = $this->runPlanProcess(
                    $adapter,
                    $freshSession,
                    $planningPrompt,
                    $systemPrompt,
                    $isRevisionRequest,
                );

                $fallbackPlan = null;
                if ($fallbackProcess->getExitCode() === 0) {
                    $fallbackPlan = $adapter->parsePlanResponse((string) $fallbackProcess->getOutput());
                }

                if ($fallbackProcess->getExitCode() === 0 && $fallbackPlan !== null) {
                    $process = $fallbackProcess;
                    $plan = $fallbackPlan;

                    $session->cli_session_id = null;
                    $session->save();
                } else {
                    $process = $fallbackProcess;
                    $plan = null;
                }
            }

            if ($process->getExitCode() !== 0) {
                $message = trim($process->getErrorOutput()) ?: 'Plan command failed.';

                if ($isRevisionRequest) {
                    $this->markRevisionState($session, 'failed', $message);
                    $writer->appendError([
                        'code' => 'PLAN_REVISION_COMMAND_FAILED',
                        'message' => $message,
                    ]);
                    $writer->appendSystem([
                        'notice' => 'plan_revision_failed',
                        'message' => $message,
                        'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    ]);

                    return;
                }

                $this->markGenerationState($session, 'failed', $message);
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'PLAN_COMMAND_FAILED',
                        'error_summary' => $message,
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'PLAN_COMMAND_FAILED',
                    'message' => $message,
                ]);

                return;
            }

            if ($plan === null) {
                $message = 'Plan response could not be parsed.';

                if ($isRevisionRequest) {
                    $this->markRevisionState($session, 'failed', $message);
                    $writer->appendError([
                        'code' => 'PLAN_REVISION_PARSE_FAILED',
                        'message' => $message,
                    ]);
                    $writer->appendSystem([
                        'notice' => 'plan_revision_failed',
                        'message' => $message,
                        'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    ]);

                    return;
                }

                $this->markGenerationState($session, 'failed', $message);
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'PLAN_PARSE_FAILED',
                        'error_summary' => $message,
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'PLAN_PARSE_FAILED',
                    'message' => $message,
                ]);

                return;
            }

            $plan = $planPayloadNormalizer->normalize($plan);
            $planValidation = $planPayloadGuard->validate($plan);
            $payloadRetryLimit = max(0, (int) config('agent.interrogation.plan_payload_retry_attempts', 2));
            $payloadRetryAttempt = 0;

            while (! (bool) ($planValidation['valid'] ?? false) && $payloadRetryAttempt < $payloadRetryLimit) { // @phpstan-ignore nullCoalesce.offset
                $payloadRetryAttempt++;
                $reason = (string) ($planValidation['reason'] ?? 'invalid plan payload'); // @phpstan-ignore nullCoalesce.offset
                $retryPrompt = $this->buildPlanPayloadRetryPrompt(
                    $planningPrompt,
                    $plan,
                    $reason,
                    $payloadRetryAttempt,
                    $payloadRetryLimit,
                );

                $retrySession = clone $session;
                $retrySession->cli_session_id = null;

                $retryProcess = $this->runPlanProcess(
                    $adapter,
                    $retrySession,
                    $retryPrompt,
                    $systemPrompt,
                    $isRevisionRequest,
                );

                if ($retryProcess->getExitCode() !== 0) {
                    continue;
                }

                $retryPlan = $adapter->parsePlanResponse((string) $retryProcess->getOutput());
                if (! is_array($retryPlan)) {
                    continue;
                }

                $plan = $planPayloadNormalizer->normalize($retryPlan);
                $planValidation = $planPayloadGuard->validate($plan);
            }

            if (! (bool) ($planValidation['valid'] ?? false)) { // @phpstan-ignore nullCoalesce.offset
                $message = 'Plan payload validation failed: '.(string) ($planValidation['reason'] ?? 'invalid plan payload'); // @phpstan-ignore nullCoalesce.offset

                if ($isRevisionRequest) {
                    $this->markRevisionState($session, 'failed', $message);
                    $writer->appendError([
                        'code' => 'PLAN_REVISION_PAYLOAD_INVALID',
                        'message' => $message,
                    ]);
                    $writer->appendSystem([
                        'notice' => 'plan_revision_failed',
                        'message' => $message,
                        'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    ]);

                    return;
                }

                $this->markGenerationState($session, 'failed', $message);
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'PLAN_PAYLOAD_INVALID',
                        'error_summary' => $message,
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'PLAN_PAYLOAD_INVALID',
                    'message' => $message,
                ]);

                return;
            }

            if ((string) $session->runner_type === 'codex') {
                [$qualityOk, $qualityIssues] = $this->validateCodexPlanQuality($plan);
                $qualityRetries = max(0, (int) config('agent.interrogation.codex_plan_quality_retries', 1));

                for ($attempt = 0; ! $qualityOk && $attempt < $qualityRetries; $attempt++) {
                    $qualityPrompt = $this->buildCodexPlanQualityRetryPrompt($planningPrompt, $plan, $qualityIssues);
                    $qualityProcess = $this->runPlanProcess(
                        $adapter,
                        $session,
                        $qualityPrompt,
                        $systemPrompt,
                        $isRevisionRequest,
                    );

                    if ($qualityProcess->getExitCode() !== 0) {
                        break;
                    }

                    $qualityPlan = $adapter->parsePlanResponse((string) $qualityProcess->getOutput());
                    if (! is_array($qualityPlan)) {
                        break;
                    }

                    $plan = $planPayloadNormalizer->normalize($qualityPlan);
                    [$qualityOk, $qualityIssues] = $this->validateCodexPlanQuality($plan);
                }

                if (! $qualityOk) {
                    $message = 'Plan quality requirements not met: '.implode('; ', $qualityIssues);

                    if ($isRevisionRequest) {
                        $this->markRevisionState($session, 'failed', $message);
                        $writer->appendError([
                            'code' => 'PLAN_REVISION_QUALITY_FAILED',
                            'message' => $message,
                            'issues' => $qualityIssues,
                        ]);
                        $writer->appendSystem([
                            'notice' => 'plan_revision_failed',
                            'message' => $message,
                            'issues' => $qualityIssues,
                            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        ]);

                        return;
                    }

                    $this->markGenerationState($session, 'failed', $message);
                    $transitions->transition(
                        (int) $session->id,
                        InterrogationSession::ACTIVE_STATUSES,
                        InterrogationSession::STATUS_FAILED,
                        [
                            'error_code' => 'PLAN_QUALITY_FAILED',
                            'error_summary' => $message,
                            'finished_at' => CarbonImmutable::now('UTC'),
                        ],
                    );

                    $session->refresh();
                    $writer = new InterrogationEventWriter($session);
                    $writer->appendError([
                        'code' => 'PLAN_QUALITY_FAILED',
                        'message' => $message,
                        'issues' => $qualityIssues,
                    ]);

                    return;
                }
            }

            if ($isRevisionRequest && $baselinePlan !== []) {
                [$revisionValid, $revisionIssues] = $this->validatePlanRevisionOutput($baselinePlan, $plan);

                if (! $revisionValid) {
                    $revisionRetryPrompt = $this->buildPlanRevisionStabilityRetryPrompt(
                        $planningPrompt,
                        $baselinePlan,
                        $plan,
                        $revisionIssues,
                    );

                    $revisionRetrySession = clone $session;
                    $revisionRetrySession->cli_session_id = null;
                    $revisionRetryProcess = $this->runPlanProcess(
                        $adapter,
                        $revisionRetrySession,
                        $revisionRetryPrompt,
                        $systemPrompt,
                        true,
                    );

                    if ($revisionRetryProcess->getExitCode() === 0) {
                        $revisionRetryPlan = $adapter->parsePlanResponse((string) $revisionRetryProcess->getOutput());
                        if (is_array($revisionRetryPlan)) {
                            $plan = $planPayloadNormalizer->normalize($revisionRetryPlan);
                            $planValidation = $planPayloadGuard->validate($plan);
                            if ((bool) ($planValidation['valid'] ?? false)) { // @phpstan-ignore nullCoalesce.offset
                                if ((string) $session->runner_type === 'codex') {
                                    [$qualityOk, $qualityIssues] = $this->validateCodexPlanQuality($plan);
                                    if (! $qualityOk) {
                                        $revisionIssues[] = 'retry revision failed codex plan quality checks';
                                    }
                                }

                                if (! isset($qualityOk) || $qualityOk) {
                                    [$revisionValid, $revisionIssues] = $this->validatePlanRevisionOutput($baselinePlan, $plan);
                                } else {
                                    $revisionValid = false;
                                }
                            } else {
                                $revisionIssues[] = 'retry revision failed payload validation: '.(string) ($planValidation['reason'] ?? 'invalid plan payload'); // @phpstan-ignore nullCoalesce.offset
                            }
                        } else {
                            $revisionIssues[] = 'retry revision response could not be parsed';
                        }
                    } else {
                        $revisionIssues[] = 'retry revision command failed';
                    }

                    if (! $revisionValid) {
                        $message = 'Plan revision was rejected because it diverged too far from the baseline: '.implode('; ', $revisionIssues);
                        $this->markRevisionState($session, 'failed', $message);
                        $writer->appendError([
                            'code' => 'PLAN_REVISION_REWRITE_REJECTED',
                            'message' => $message,
                            'issues' => $revisionIssues,
                        ]);
                        $writer->appendSystem([
                            'notice' => 'plan_revision_failed',
                            'message' => $message,
                            'issues' => $revisionIssues,
                            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        ]);

                        return;
                    }
                }
            }

            $session->plan_json = $plan;
            $session->approved_at = null;

            $metadata = (array) ($session->metadata_json ?? []);

            $summaryPath = null;
            if (is_array($session->summary_json) && $session->summary_json !== []) {
                $summaryPath = $exportService->exportSummary($session);
            }
            $planPath = $exportService->exportPlan($session);

            $metadata['exports'] = [
                'summary' => $summaryPath,
                'plan' => $planPath,
            ];
            $metadata['build'] = [
                'status' => 'idle',
                'task_count' => 0,
                'active_task_id' => null,
                'active_run_id' => null,
                'tasks_approved_at' => null,
                'tasks_approved_by_user_id' => null,
                'task_provider_sync' => [
                    'status' => 'idle',
                    'driver' => null,
                    'project_mode' => 'create_new',
                    'project_id' => null,
                    'project_name' => null,
                    'project_url' => null,
                    'synced_task_count' => 0,
                    'error' => null,
                    'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ],
                'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];
            if ($isRevisionRequest) {
                $metadata['plan'] = array_merge(
                    is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
                    [
                        'revision_status' => 'idle',
                        'revision_completed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        'revision_updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        'revision_error' => null,
                    ],
                );
            } else {
                $metadata['plan'] = array_merge(
                    is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
                    [
                        'generation_status' => 'idle',
                        'generation_completed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        'generation_updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        'generation_error' => null,
                    ],
                );
            }

            InterrogationBuildTask::query()
                ->where('interrogation_session_id', $session->id)
                ->delete();

            $session->metadata_json = $metadata;
            $session->status = InterrogationSession::STATUS_PLANNING;
            $session->phase = InterrogationSession::PHASE_PLANNING;
            $session->finished_at = null;
            $session->save();

            $writer = new InterrogationEventWriter($session);
            $writer->appendPlan($plan);
            $writer->appendSystem([
                'notice' => 'plan_ready',
                'summary_path' => $summaryPath,
                'plan_path' => $planPath,
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            if ($isRevisionRequest && isset($session) && $session instanceof InterrogationSession) { // @phpstan-ignore instanceof.alwaysTrue, isset.variable
                $this->markRevisionState($session, 'failed', $throwable->getMessage());
                $writer->appendError([
                    'code' => 'PLAN_REVISION_RUNTIME_EXCEPTION',
                    'message' => $throwable->getMessage(),
                ]);
                $writer->appendSystem([
                    'notice' => 'plan_revision_failed',
                    'message' => $throwable->getMessage(),
                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ]);

                return;
            }

            $this->markGenerationState($session, 'failed', $throwable->getMessage());
            $transitions->transition(
                (int) $session->id,
                InterrogationSession::ACTIVE_STATUSES,
                InterrogationSession::STATUS_FAILED,
                [
                    'error_code' => 'PLAN_RUNTIME_EXCEPTION',
                    'error_summary' => $throwable->getMessage(),
                    'finished_at' => CarbonImmutable::now('UTC'),
                ],
            );

            $session->refresh();
            $writer = new InterrogationEventWriter($session);
            $writer->appendError([
                'code' => 'PLAN_RUNTIME_EXCEPTION',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function markRevisionState(InterrogationSession $session, string $status, ?string $error = null): void
    {
        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $plan = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'revision_status' => $status,
                'revision_updated_at' => $now,
            ],
        );

        if ($status === 'running') {
            $plan['revision_started_at'] = $now;
            $plan['revision_error'] = null;
        }

        if ($status === 'failed') {
            $plan['revision_error'] = $error ?: 'Plan revision failed.';
        }

        $metadata['plan'] = $plan;
        $session->metadata_json = $metadata;
        $session->save();
    }

    private function markGenerationState(InterrogationSession $session, string $status, ?string $error = null): void
    {
        $now = CarbonImmutable::now('UTC')->toIso8601String();
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $plan = array_merge(
            is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
            [
                'generation_status' => $status,
                'generation_updated_at' => $now,
            ],
        );

        if ($status === 'running') {
            $plan['generation_started_at'] = $now;
            $plan['generation_error'] = null;
        }

        if ($status === 'failed') {
            $plan['generation_error'] = $error ?: 'Plan generation failed.';
        }

        if ($status === 'idle') {
            $plan['generation_completed_at'] = $now;
            $plan['generation_error'] = null;
        }

        $metadata['plan'] = $plan;
        $session->metadata_json = $metadata;
        $session->save();
    }

    private function runPlanProcess(
        InterrogationRunnerAdapter $adapter,
        InterrogationSession $session,
        string $planningPrompt,
        string $systemPrompt,
        bool $isRevisionRequest,
    ): Process {
        $process = new Process(
            $adapter->buildPlanCommand($session, $planningPrompt, $systemPrompt),
            (string) $session->project_directory,
            $adapter->buildEnvironment($session),
        );
        $process->setTimeout($isRevisionRequest ? 900 : 600);
        $process->run();

        return $process;
    }

    /**
     * @param  array<string, mixed>  $baselinePlan
     */
    private function buildPlanningPrompt(InterrogationSession $session, bool $isRevisionRequest, array $baselinePlan = []): string
    {
        $planningPrompt = trim((string) ($isRevisionRequest ? $this->revisionPrompt : ''));
        if ($planningPrompt === '') {
            $planningPrompt = 'Generate a complete implementation plan JSON with plan_markdown, sections, risks, and assumptions from the confirmed summary.';
        }

        $planningPrompt .= "\n\n"
            .'Locked summary baseline (authoritative context):'."\n"
            .$this->summaryContextBlock($session)
            ."\n\n"
            .'Session metadata context:'."\n"
            .$this->metadataContextBlock($session)
            ."\n\n"
            .'Hard constraints: '
            .'Do not include any estimates or timelines in any field. '
            .'Do not mention total effort, days, weeks, months, ETA, critical path, or parallelization schedules. '
            .'Provide sequence/dependency order only, without duration predictions. '
            .'For any user-facing or operator-facing capability, include explicit implementation detail for route/page/navigation exposure and in-app discoverability acceptance checks (not API-only completion). '
            .'Return finalized planning output only; never return process-status narration (for example "reviewing baseline", "I will rewrite", or "next I will").';

        if ($isRevisionRequest && $baselinePlan !== []) {
            $planningPrompt .= "\n\n"
                .'Revision mode rules: apply targeted edits to the current plan baseline. '
                .'Do not rewrite unrelated sections. Preserve existing structure and depth unless the revision request explicitly asks otherwise.'
                ."\n\n"
                .'Current plan baseline (edit in place):'."\n"
                .$this->planContextBlock($baselinePlan);
        }

        if ((string) $session->runner_type === 'codex') {
            $planningPrompt .= "\n\n"
                .'Codex parity requirements (match Claude plan depth): '
                .'Produce a detailed, implementation-ready plan_markdown with explicit headings and concrete decisions. '
                .'Include sections for: scope boundary, architecture changes, data model/migrations, API/tool contracts, event contracts, '
                .'authorization/scope enforcement, failure/retry behavior, observability, test strategy (unit/feature/integration), '
                .'backward compatibility, rollout and rollback controls, and user/operator surface exposure (routes/pages/navigation/discoverability). '
                .'In each section, list specific implementation actions and impacted files/components where known. '
                .'Keep sections, risks, and assumptions comprehensive and non-overlapping; avoid generic filler text.';
        }

        return $planningPrompt;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function planContextBlock(array $plan): string
    {
        $planMarkdown = trim((string) ($plan['plan_markdown'] ?? ''));
        $sections = $this->normalizeStringList($plan['sections'] ?? null);
        $risks = $this->normalizeStringList($plan['risks'] ?? null);
        $assumptions = $this->normalizeStringList($plan['assumptions'] ?? null);

        $lines = [];
        if ($planMarkdown !== '') {
            $lines[] = "Plan markdown:\n".$planMarkdown;
        }
        if ($sections !== []) {
            $lines[] = "Sections:\n- ".implode("\n- ", $sections);
        }
        if ($risks !== []) {
            $lines[] = "Risks:\n- ".implode("\n- ", $risks);
        }
        if ($assumptions !== []) {
            $lines[] = "Assumptions:\n- ".implode("\n- ", $assumptions);
        }

        return $lines === [] ? '- no plan baseline fields present' : implode("\n\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $candidate
     * @return array{0:bool,1:array<int,string>}
     */
    private function validatePlanRevisionOutput(array $baseline, array $candidate): array
    {
        $issues = [];

        $baselineMarkdownLength = mb_strlen((string) ($baseline['plan_markdown'] ?? ''));
        $candidateMarkdownLength = mb_strlen((string) ($candidate['plan_markdown'] ?? ''));
        if ($baselineMarkdownLength >= 2000 && $candidateMarkdownLength < (int) floor($baselineMarkdownLength * 0.60)) {
            $issues[] = 'plan_markdown shrank by more than 40% from baseline';
        }

        $baselineSections = count($this->normalizeStringList($baseline['sections'] ?? null));
        $candidateSections = count($this->normalizeStringList($candidate['sections'] ?? null));
        if ($baselineSections >= 6 && $candidateSections < (int) floor($baselineSections * 0.60)) {
            $issues[] = sprintf('sections list shrank too aggressively (%d -> %d)', $baselineSections, $candidateSections);
        }

        $baselineRisks = count($this->normalizeStringList($baseline['risks'] ?? null));
        $candidateRisks = count($this->normalizeStringList($candidate['risks'] ?? null));
        if ($baselineRisks > 0 && $candidateRisks === 0) {
            $issues[] = 'risks list was dropped from baseline';
        }

        $baselineAssumptions = count($this->normalizeStringList($baseline['assumptions'] ?? null));
        $candidateAssumptions = count($this->normalizeStringList($candidate['assumptions'] ?? null));
        if ($baselineAssumptions > 0 && $candidateAssumptions === 0) {
            $issues[] = 'assumptions list was dropped from baseline';
        }

        return [$issues === [], $issues];
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $candidate
     * @param  array<int, string>  $issues
     */
    private function buildPlanRevisionStabilityRetryPrompt(
        string $basePrompt,
        array $baseline,
        array $candidate,
        array $issues,
    ): string {
        $issuesText = $issues === [] ? '- output diverged from revision expectations' : '- '.implode("\n- ", $issues);

        return $basePrompt
            ."\n\nPlan revision quality check failed."
            ."\nProblems detected:\n".$issuesText
            ."\n\nMandatory correction rules:"
            ."\n- Keep baseline plan structure unless explicitly changed by the revision request."
            ."\n- Preserve depth and implementation detail across unaffected sections."
            ."\n- Do not remove risks/assumptions without explicit request."
            ."\n\nBaseline plan:\n".$this->planContextBlock($baseline)
            ."\n\nRejected revision plan:\n".$this->planContextBlock($candidate);
    }

    private function summaryContextBlock(InterrogationSession $session): string
    {
        $summary = is_array($session->summary_json) ? $session->summary_json : [];
        if ($summary === []) {
            return '- No summary_json is stored.';
        }

        $summaryMarkdown = trim((string) ($summary['summary_markdown'] ?? ''));
        $goals = $this->normalizeStringList($summary['goals'] ?? null);
        $constraints = $this->normalizeStringList($summary['constraints'] ?? null);
        $acceptance = $this->normalizeStringList($summary['acceptance_criteria'] ?? null);
        $openQuestions = $this->normalizeStringList($summary['open_questions'] ?? null);

        $lines = [];
        if ($summaryMarkdown !== '') {
            $lines[] = "Summary markdown:\n".$summaryMarkdown;
        }
        if ($goals !== []) {
            $lines[] = "Goals:\n- ".implode("\n- ", $goals);
        }
        if ($constraints !== []) {
            $lines[] = "Constraints:\n- ".implode("\n- ", $constraints);
        }
        if ($acceptance !== []) {
            $lines[] = "Acceptance criteria:\n- ".implode("\n- ", $acceptance);
        }
        if ($openQuestions !== []) {
            $lines[] = "Open questions (must address explicitly):\n- ".implode("\n- ", $openQuestions);
        }

        return $lines === [] ? '- summary_json exists but has no usable fields.' : implode("\n\n", $lines);
    }

    private function metadataContextBlock(InterrogationSession $session): string
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];

        $context = [
            'session_id' => (int) $session->id,
            'interrogation_type' => (string) $session->interrogation_type,
            'runner_type' => (string) $session->runner_type,
            'project_directory' => (string) $session->project_directory,
            'plan_metadata' => is_array($metadata['plan'] ?? null) ? $metadata['plan'] : [],
        ];

        return json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $normalized = trim($item);
            if ($normalized === '') {
                continue;
            }

            $items[] = $normalized;
        }

        return array_values(array_unique($items));
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{0:bool,1:array<int,string>}
     */
    private function validateCodexPlanQuality(array $plan): array
    {
        $issues = [];
        $markdown = trim((string) ($plan['plan_markdown'] ?? ''));
        $sections = is_array($plan['sections'] ?? null) ? $plan['sections'] : [];

        $minChars = max(300, (int) config('agent.interrogation.codex_plan_min_markdown_chars', 2500));
        if (mb_strlen($markdown) < $minChars) {
            $issues[] = 'plan_markdown is too short for implementation depth';
        }

        $minSections = max(3, (int) config('agent.interrogation.codex_plan_min_sections', 8));
        if (count($sections) < $minSections) {
            $issues[] = 'sections list is too small';
        }

        $referenceCount = $this->countConcreteReferences($markdown);
        $minReferences = max(1, (int) config('agent.interrogation.codex_plan_min_concrete_references', 6));
        if ($referenceCount < $minReferences) {
            $issues[] = 'plan lacks concrete file/API/component references';
        }

        return [$issues === [], $issues];
    }

    private function countConcreteReferences(string $markdown): int
    {
        if ($markdown === '') {
            return 0;
        }

        $matches = [];
        preg_match_all('/\b(?:app|routes|config|database|resources|tests|docs)\/[A-Za-z0-9_\/\.\-]+\b/', $markdown, $matches);
        $paths = array_values(array_unique($matches[0] ?? [])); // @phpstan-ignore nullCoalesce.offset

        $componentMatches = [];
        preg_match_all('/\b[A-Z][A-Za-z0-9]+(?:Controller|Service|Policy|Request|Job|Model|Adapter|Normalizer|Generator|Runner|Session|Event|Test)\b/', $markdown, $componentMatches);
        $components = array_values(array_unique($componentMatches[0] ?? [])); // @phpstan-ignore nullCoalesce.offset

        $endpointMatches = [];
        preg_match_all('/\b(?:GET|POST|PUT|PATCH|DELETE)\s+\/[A-Za-z0-9_\/\-\{\}]+|\b\/agent\/api\/v1\/[A-Za-z0-9_\/\-\{\}]+\b/', $markdown, $endpointMatches);
        $endpoints = array_values(array_unique($endpointMatches[0] ?? [])); // @phpstan-ignore nullCoalesce.offset

        return count($paths) + count($components) + count($endpoints);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<int, string>  $issues
     */
    private function buildCodexPlanQualityRetryPrompt(string $basePrompt, array $plan, array $issues): string
    {
        $issuesText = $issues === []
            ? '- plan is too generic'
            : '- '.implode("\n- ", $issues);

        $currentPlan = trim((string) ($plan['plan_markdown'] ?? ''));
        if ($currentPlan === '') {
            $currentPlan = '[empty]';
        }

        return $basePrompt
            ."\n\nRewrite the plan with substantially more technical detail."
            ."\nQuality issues to fix:\n{$issuesText}"
            ."\n\nMandatory rewrite rules:"
            ."\n- Include concrete file-level targets where known (for example app/...php, routes/api.php, resources/js/..., tests/...)."
            ."\n- Include explicit API/action contracts and error behavior where relevant."
            ."\n- Include explicit validation/authorization rules and test coverage mapping."
            ."\n- If functionality is user-facing or operator-facing, include route/page/nav wiring and in-app discoverability validation."
            ."\n- Keep sequence/dependency order and preserve no-estimates/no-timelines policy."
            ."\n\nCurrent plan to improve:\n{$currentPlan}";
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function buildPlanPayloadRetryPrompt(
        string $basePrompt,
        array $plan,
        string $validationReason,
        int $attempt,
        int $limit,
    ): string {
        $currentPlan = trim((string) ($plan['plan_markdown'] ?? ''));
        if ($currentPlan === '') {
            $currentPlan = '[empty]';
        }

        return $basePrompt
            ."\n\nYour previous output failed validation on attempt {$attempt}/{$limit}."
            ."\nValidation failure reason: {$validationReason}."
            ."\n\nReturn only final structured plan content in the output schema."
            ."\nDo not include process narration, intent statements, or status updates."
            ."\nMandatory output requirements:"
            ."\n- `plan_markdown` must be a complete Markdown plan with multiple headings."
            ."\n- Include numbered and/or bulleted implementation steps."
            ."\n- `sections`, `risks`, and `assumptions` must be non-empty arrays."
            ."\n- Keep content implementation-ready and concrete."
            ."\n\nRejected output to replace:\n{$currentPlan}";
    }

    /**
     * Run adversarial review with pass/revise verdict handling only.
     *
     * Plan review does NOT support needs_clarification; any such verdict
     * is treated as revise. In shadow mode (review_warn_only=true), logs
     * findings to metadata_json and emits events but does not gate artifacts.
     *
     * In gating mode (review_warn_only=false):
     * - pass: returns null (success, continue with plan persistence)
     * - revise: returns review payload for regeneration context
     *
     * Critical issues auto-escalate pass to revise. Max 2 retries (configurable)
     * enforced before failure.
     *
     * @param  array<string, mixed>  $planCandidate
     * @return array<string, mixed>|null Null on pass/exhausted; review payload on revise
     */
    private function runAdversarialReview( // @phpstan-ignore method.unused
        InterrogationSession $session,
        array $planCandidate,
        InterrogationEventWriter $writer
    ): ?array {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::ADVERSARIAL_REVIEW_ENABLED)) {
            return null;
        }

        $warnOnly = (bool) config('agent.interrogation.review_warn_only', false);
        $maxRetries = (int) config('agent.interrogation.plan_review_max_retries', 2);

        try {
            $reviewerService = app(AdversarialReviewerService::class);

            // Get locked summary from metadata
            $metadata = $session->metadata_json ?? [];
            $lockedSummary = $metadata['summary'] ?? [];

            $reviewPayload = $reviewerService->reviewPlan($session, $planCandidate, $lockedSummary);

            // Store review findings in metadata
            $metadata['plan'] = $metadata['plan'] ?? [];
            $attempt = ($metadata['plan']['review_attempts'] ?? 0) + 1;
            $metadata['plan']['review_attempts'] = $attempt;
            $metadata['plan']['last_review'] = $reviewPayload;
            $metadata['plan']['review_history'] = $metadata['plan']['review_history'] ?? [];
            $metadata['plan']['review_history'][] = $reviewPayload;

            $verdict = $reviewPayload['verdict'];
            $confidence = (float) ($reviewPayload['confidence'] ?? 1.0);

            // Check for critical issue auto-escalation
            if ($verdict === 'pass' && $this->hasCriticalIssue($reviewPayload['issues'] ?? [])) {
                $verdict = 'revise';
            }

            // needs_clarification is NOT allowed for plan review - treat as revise
            if ($verdict === 'needs_clarification') {
                $verdict = 'revise';
            }

            // Emit review event
            $writer->appendPlanReview([
                'status' => $warnOnly ? 'shadow_'.$verdict : $verdict,
                'verdict' => $verdict,
                'attempt' => $attempt,
                'issue_count' => count($reviewPayload['issues'] ?? []),
                'confidence' => $confidence,
                'shadow_mode' => $warnOnly,
            ]);

            // Shadow mode: store but don't gate
            if ($warnOnly) {
                $metadata['plan']['review_status'] = 'shadow_'.$verdict;
                $session->update(['metadata_json' => $metadata]);

                return null;
            }

            // Handle verdict
            if ($verdict === 'pass') {
                $metadata['plan']['review_status'] = 'passed';
                $session->update(['metadata_json' => $metadata]);

                return null; // Success, continue with plan persistence
            }

            // Revise
            if ($attempt >= $maxRetries) {
                $metadata['plan']['review_status'] = 'accepted_with_warnings';
                $session->update(['metadata_json' => $metadata]);

                $writer->appendSystem([
                    'notice' => 'plan_review_exhausted',
                    'message' => 'Adversarial plan review exhausted retries ('.$maxRetries.'). Accepting current plan with warnings.',
                    'issues' => $reviewPayload['issues'] ?? [],
                    'required_changes' => $reviewPayload['required_changes'] ?? [],
                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ]);

                return null;
            }

            $metadata['plan']['review_status'] = 'revising';
            $session->update(['metadata_json' => $metadata]);

            // Return review payload for regeneration context
            return $reviewPayload;

        } catch (\Throwable $e) {
            // In shadow mode, log error but don't fail the job
            if ($warnOnly) {
                report($e);

                // Store error in metadata for debugging
                $metadata = $session->metadata_json ?? [];
                $metadata['plan'] = $metadata['plan'] ?? [];
                $metadata['plan']['review_error'] = [
                    'message' => $e->getMessage(),
                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ];
                $session->update(['metadata_json' => $metadata]);

                return null;
            }

            throw $e;
        }
    }

    /**
     * Check if any issue has critical severity.
     *
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function hasCriticalIssue(array $issues): bool
    {
        foreach ($issues as $issue) {
            if (strtolower((string) ($issue['severity'] ?? '')) === 'critical') {
                return true;
            }
        }

        return false;
    }
}
