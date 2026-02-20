<?php

namespace App\Jobs;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Interrogation\AdapterFactory;
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

        if ($session === null) {
            return;
        }

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true) && $session->status !== InterrogationSession::STATUS_COMPLETED) {
            if (! $isRevisionRequest || $session->status !== InterrogationSession::STATUS_FAILED) {
                return;
            }
        }

        $writer = new InterrogationEventWriter($session);

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

            $adapter = $adapterFactory->make((string) $session->runner_type);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'planning');

            if ($isRevisionRequest) {
                $this->markRevisionState($session, 'running');
            } else {
                $this->markGenerationState($session, 'running');
            }

            $planningPrompt = $this->buildPlanningPrompt($session, $isRevisionRequest);

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

            $shouldRetryWithoutResume = is_string($planningSession->cli_session_id)
                && trim($planningSession->cli_session_id) !== ''
                && ($process->getExitCode() !== 0 || $plan === null);

            if ($shouldRetryWithoutResume) {
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

            while (! (bool) ($planValidation['valid'] ?? false) && $payloadRetryAttempt < $payloadRetryLimit) {
                $payloadRetryAttempt++;
                $reason = (string) ($planValidation['reason'] ?? 'invalid plan payload');
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

            if (! (bool) ($planValidation['valid'] ?? false)) {
                $message = 'Plan payload validation failed: '.(string) ($planValidation['reason'] ?? 'invalid plan payload');

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

            if ($isRevisionRequest && isset($session) && $session instanceof InterrogationSession) {
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

    private function buildPlanningPrompt(InterrogationSession $session, bool $isRevisionRequest): string
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
            .'Return finalized planning output only; never return process-status narration (for example "reviewing baseline", "I will rewrite", or "next I will").';

        if ((string) $session->runner_type === 'codex') {
            $planningPrompt .= "\n\n"
                .'Codex parity requirements (match Claude plan depth): '
                .'Produce a detailed, implementation-ready plan_markdown with explicit headings and concrete decisions. '
                .'Include sections for: scope boundary, architecture changes, data model/migrations, API/tool contracts, event contracts, '
                .'authorization/scope enforcement, failure/retry behavior, observability, test strategy (unit/feature/integration), '
                .'backward compatibility, rollout and rollback controls. '
                .'In each section, list specific implementation actions and impacted files/components where known. '
                .'Keep sections, risks, and assumptions comprehensive and non-overlapping; avoid generic filler text.';
        }

        return $planningPrompt;
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
        $paths = array_values(array_unique($matches[0] ?? []));

        $componentMatches = [];
        preg_match_all('/\b[A-Z][A-Za-z0-9]+(?:Controller|Service|Policy|Request|Job|Model|Adapter|Normalizer|Generator|Runner|Session|Event|Test)\b/', $markdown, $componentMatches);
        $components = array_values(array_unique($componentMatches[0] ?? []));

        $endpointMatches = [];
        preg_match_all('/\b(?:GET|POST|PUT|PATCH|DELETE)\s+\/[A-Za-z0-9_\/\-\{\}]+|\b\/agent\/api\/v1\/[A-Za-z0-9_\/\-\{\}]+\b/', $markdown, $endpointMatches);
        $endpoints = array_values(array_unique($endpointMatches[0] ?? []));

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
}
