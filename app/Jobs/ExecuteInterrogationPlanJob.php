<?php

namespace App\Jobs;

use App\Models\InterrogationBuildTask;
use App\Models\InterrogationSession;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use App\Support\Interrogation\ExportService;
use App\Support\Interrogation\InterrogationEventWriter;
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
            }

            $planningPrompt = trim((string) $this->revisionPrompt);
            if ($planningPrompt === '') {
                $planningPrompt = 'Generate an implementation plan JSON with plan_markdown, sections, risks, and assumptions based on the confirmed summary.';
            }
            $planningPrompt .= "\n\n"
                .'Hard constraints: '
                .'Do not include any estimates or timelines in any field. '
                .'Do not mention total effort, days, weeks, months, ETA, critical path, or parallelization schedules. '
                .'Provide sequence/dependency order only, without duration predictions.';

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

            $process = $this->runPlanProcess(
                $adapter,
                $session,
                $planningPrompt,
                $systemPrompt,
                $isRevisionRequest,
            );

            $plan = null;
            if ($process->getExitCode() === 0) {
                $plan = $adapter->parsePlanResponse((string) $process->getOutput());
            }

            $shouldRetryWithoutResume = is_string($session->cli_session_id)
                && trim($session->cli_session_id) !== ''
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
}
