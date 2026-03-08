<?php

namespace App\Jobs;

use App\Models\InterrogationSession;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\AdversarialReviewerService;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use App\Support\Interrogation\ConversationReconstructor;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\SessionStateTransitionService;
use App\Support\Interrogation\SummaryOpenQuestionQueueService;
use App\Support\Interrogation\SummaryPayloadNormalizer;
use App\Support\Interrogation\SystemPromptResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\Process\Process;

class ExecuteInterrogationSummaryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $sessionId, public ?string $revisionNotes = null)
    {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(
        AdapterFactory $adapterFactory,
        ConversationReconstructor $reconstructor,
        SummaryPayloadNormalizer $summaryPayloadNormalizer,
        SystemPromptResolver $promptResolver,
        SessionStateTransitionService $transitions,
        FeatureFlagManager $featureFlags,
    ): void {
        $session = InterrogationSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true)) {
            return;
        }

        $summaryQueueActive = (bool) data_get($session->metadata_json, 'summary_open_question_queue.active', false);
        if ($summaryQueueActive) {
            return;
        }

        if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
            return;
        }

        $writer = new InterrogationEventWriter($session);
        $isRevisionRequest = is_string($this->revisionNotes) && trim($this->revisionNotes) !== '';
        $baselineSummary = is_array($session->summary_json) && $session->summary_json !== []
            ? $summaryPayloadNormalizer->normalize($session->summary_json)
            : [];

        try {
            $adapter = $adapterFactory->make((string) $session->runner_type, $session->model);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'summary');
            $history = $reconstructor->reconstruct($session);
            $summaryPrompt = 'Produce a structured summary JSON for this discovery session. '
                .'Populate ALL schema fields exactly: summary_markdown, goals, constraints, acceptance_criteria, open_questions, private_notes. '
                .'Return arrays as arrays of plain strings (never embed them inside summary_markdown). '
                .'Do not emit XML-like parameter tags such as <parameter ...>. '
                .'Make the summary implementation-ready and comprehensive (not abbreviated): include concrete decisions, entities, services, config values, and acceptance criteria. '
                .'Do not include estimates or timelines (no days/weeks/months, ETA, total effort, critical path, or parallelization schedule).';

            if ($isRevisionRequest) {
                $summaryPrompt = 'Revise the structured summary JSON for this discovery session using the following user amendment notes. '
                    .'Keep the same schema fields (summary_markdown, goals, constraints, acceptance_criteria, open_questions, private_notes). '
                    .'Return arrays as real JSON arrays of strings, never embedded inside summary_markdown. '
                    .'Do not emit XML-like parameter tags such as <parameter ...>. '
                    .'Treat this as a targeted amendment pass, not a rewrite from scratch. Preserve existing structure and detail unless the notes explicitly request changes. '
                    .'Keep full implementation detail (decisions, entities, services, config, acceptance criteria); do not collapse to high-level prose. '
                    .'Do not include estimates or timelines (no days/weeks/months, ETA, total effort, critical path, or parallelization schedule). '
                    .'Only keep items in open_questions that are truly unresolved after applying the notes.'."\n\n"
                    .'Amendment notes:'."\n".trim($this->revisionNotes);

                if ($baselineSummary !== []) {
                    $summaryPrompt .= "\n\nCurrent summary baseline (apply amendments in place; do not drop unaffected content):\n"
                        .$this->summaryContextBlock($baselineSummary);
                }
            }

            $summaryPrompt .= "\n\n"
                .'Use the authoritative session transcript below as required context. It contains full discovery + reinterrogation questions/answers. '
                .'Incorporate resolved answers into the summary and remove any open question that has been answered.'."\n\n"
                .'Session transcript:'."\n"
                .$history;

            $process = $this->runSummaryProcess($adapter, $session, $summaryPrompt, $systemPrompt);
            $summary = null;
            $retriedWithoutResume = false;

            if ($process->getExitCode() === 0) {
                $summary = $adapter->parseSummaryResponse((string) $process->getOutput());
            }

            if ($summary === null) {
                $fallbackPrompt = $summaryPrompt;

                $freshSession = clone $session;
                $freshSession->cli_session_id = null;
                $fallbackProcess = $this->runSummaryProcess($adapter, $freshSession, $fallbackPrompt, $systemPrompt);
                $retriedWithoutResume = true;

                if ($fallbackProcess->getExitCode() === 0) {
                    $summary = $adapter->parseSummaryResponse((string) $fallbackProcess->getOutput());
                    $process = $fallbackProcess;
                } else {
                    $process = $fallbackProcess;
                }
            }

            if ($summary === null) {
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'SUMMARY_PARSE_FAILED',
                        'error_summary' => trim((string) $process->getErrorOutput()) !== ''
                            ? trim((string) $process->getErrorOutput())
                            : 'Summary response could not be parsed.',
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => ((int) $process->getExitCode() !== 0) ? 'SUMMARY_COMMAND_FAILED' : 'SUMMARY_PARSE_FAILED',
                    'message' => trim((string) $process->getErrorOutput()) !== ''
                        ? trim((string) $process->getErrorOutput())
                        : 'Summary response could not be parsed.',
                ]);

                return;
            }

            $summary = $summaryPayloadNormalizer->normalize($summary);

            if ($isRevisionRequest && $baselineSummary !== []) {
                $summary = $this->preserveUnrequestedRevisionFields($baselineSummary, $summary);
                [$revisionStable, $revisionIssues] = $this->validateRevisionOutput($baselineSummary, $summary);
                if (! $revisionStable) {
                    $retryPrompt = $this->buildRevisionStabilityPrompt($summaryPrompt, $baselineSummary, $summary, $revisionIssues);
                    $retrySession = clone $session;
                    $retrySession->cli_session_id = null;

                    $retryProcess = $this->runSummaryProcess($adapter, $retrySession, $retryPrompt, $systemPrompt);
                    if ($retryProcess->getExitCode() === 0) {
                        $retrySummary = $adapter->parseSummaryResponse((string) $retryProcess->getOutput());
                        if (is_array($retrySummary)) {
                            $summary = $summaryPayloadNormalizer->normalize($retrySummary);
                            $summary = $this->preserveUnrequestedRevisionFields($baselineSummary, $summary);
                            [$revisionStable, $revisionIssues] = $this->validateRevisionOutput($baselineSummary, $summary);
                        }
                    }

                    if (! $revisionStable) {
                        $writer->appendSystem([
                            'notice' => 'summary_revision_rejected',
                            'message' => 'Summary revision was rejected because it removed too much baseline detail. Keeping previous summary.',
                            'issues' => $revisionIssues,
                            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        ]);

                        return;
                    }
                }
            }

            $metadata = (array) ($session->metadata_json ?? []);
            $metadata['summary_ready'] = true;
            $metadata['summary_generated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

            $session->summary_json = $summary;
            $session->metadata_json = $metadata;
            if ($retriedWithoutResume && is_string($session->cli_session_id) && trim($session->cli_session_id) !== '') {
                $session->cli_session_id = null;
            }
            $session->save();

            $writer->appendSummary($summary);

            // Run adversarial review — may gate the summary if in gating mode.
            $reviewResult = null;
            if ($featureFlags->enabled(FeatureFlagManager::ADVERSARIAL_REVIEW_ENABLED)) {
                $reviewResult = $this->runAdversarialReview($session, $summary, $writer);
            }

            // When gating mode returns a revise verdict, auto-dispatch a revision
            // pass using the required_changes as amendment notes.
            if (is_array($reviewResult) && ! isset($reviewResult['halt'])) {
                $requiredChanges = array_filter(
                    (array) ($reviewResult['required_changes'] ?? []),
                    static fn ($v): bool => is_string($v) && trim($v) !== ''
                );

                if ($requiredChanges !== []) {
                    $revisionNotes = "Adversarial review required changes:\n- "
                        .implode("\n- ", array_values($requiredChanges));

                    ExecuteInterrogationSummaryJob::dispatch((int) $session->id, $revisionNotes);

                    return;
                }
            }

            // If review halted for clarification, don't mark summary as ready.
            if (is_array($reviewResult) && isset($reviewResult['halt'])) {
                return;
            }

            // Guard against stale job: user may have approved the summary and
            // moved to the plan phase while this long-running job was in flight.
            $session->refresh();
            if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
                return;
            }

            // Auto-continue interrogation if the queue-based re-interrogation
            // produced a summary that still contains open questions.
            if ($this->autoReinterrogateIfNeeded($session, $summary, $transitions, $writer)) {
                return;
            }

            $writer->appendSystem([
                'notice' => 'summary_ready',
                'message' => 'Summary generated. Awaiting user confirmation.',
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

            // Don't crash a session that has already moved past the summary phase.
            $session->refresh();
            if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
                return;
            }

            $transitions->transition(
                (int) $session->id,
                InterrogationSession::ACTIVE_STATUSES,
                InterrogationSession::STATUS_FAILED,
                [
                    'error_code' => 'SUMMARY_RUNTIME_EXCEPTION',
                    'error_summary' => $throwable->getMessage(),
                    'finished_at' => CarbonImmutable::now('UTC'),
                ],
            );

            $session->refresh();
            $writer = new InterrogationEventWriter($session);
            $writer->appendError([
                'code' => 'SUMMARY_RUNTIME_EXCEPTION',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * If the auto-continue flag is set and the freshly generated summary still
     * contains open questions, automatically re-queue them and transition back
     * to interrogation without requiring user intervention.
     *
     * @param  array<string, mixed>  $summary
     */
    private function autoReinterrogateIfNeeded(
        InterrogationSession $session,
        array $summary,
        SessionStateTransitionService $transitions,
        InterrogationEventWriter $writer,
    ): bool {
        $queueService = app(SummaryOpenQuestionQueueService::class);
        $flag = $queueService->consumeAutoReinterrogationFlag($session);

        if (! $flag['active']) {
            return false;
        }

        $openQuestions = is_array($summary['open_questions'] ?? null)
            ? array_values($summary['open_questions'])
            : [];
        $normalized = $queueService->normalizeOpenQuestionList($openQuestions);

        if ($normalized === []) {
            return false;
        }

        // Transition back to interrogation and queue remaining open questions.
        $moved = $transitions->transitionPhase(
            (int) $session->id,
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_INTERROGATION,
            InterrogationSession::STATUS_INTERROGATING,
            [InterrogationSession::STATUS_SUMMARIZING, InterrogationSession::STATUS_PAUSED],
        );

        if (! $moved) {
            return false;
        }

        $session->refresh();

        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition(
            InterrogationSession::PHASE_SUMMARY,
            InterrogationSession::PHASE_INTERROGATION,
            (string) $session->status,
            [
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'source' => 'auto_continue_open_questions',
                'remaining_open_questions' => count($normalized),
            ]
        );

        $writer->appendSystem([
            'notice' => 'auto_continue_interrogation',
            'message' => count($normalized).' open question(s) remain after summary regeneration. Automatically continuing interrogation.',
        ]);

        $queue = $queueService->buildQueue($normalized, $flag['focus']);
        $queueService->persistQueue($session, $queue);
        $queueService->dispatchNextFromQueue($session);

        return true;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function summaryContextBlock(array $summary): string
    {
        $summaryMarkdown = trim((string) ($summary['summary_markdown'] ?? ''));
        $goals = $this->normalizeStringList($summary['goals'] ?? null);
        $constraints = $this->normalizeStringList($summary['constraints'] ?? null);
        $acceptance = $this->normalizeStringList($summary['acceptance_criteria'] ?? null);
        $openQuestions = $this->normalizeStringList($summary['open_questions'] ?? null);
        $privateNotes = trim((string) ($summary['private_notes'] ?? ''));

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
            $lines[] = "Open questions:\n- ".implode("\n- ", $openQuestions);
        }
        if ($privateNotes !== '') {
            $lines[] = "Private notes:\n".$privateNotes;
        }

        return $lines === [] ? '- no baseline fields present' : implode("\n\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $candidate
     * @return array{0:bool,1:array<int,string>}
     */
    private function validateRevisionOutput(array $baseline, array $candidate): array
    {
        $issues = [];

        $baselineMarkdownLength = mb_strlen((string) ($baseline['summary_markdown'] ?? ''));
        $candidateMarkdownLength = mb_strlen((string) ($candidate['summary_markdown'] ?? ''));
        if ($baselineMarkdownLength >= 2000 && $candidateMarkdownLength < (int) floor($baselineMarkdownLength * 0.60)) {
            $issues[] = 'summary_markdown shrank by more than 40% from baseline';
        }

        foreach (['goals', 'constraints', 'acceptance_criteria'] as $field) {
            $baselineCount = count($this->normalizeStringList($baseline[$field] ?? null));
            $candidateCount = count($this->normalizeStringList($candidate[$field] ?? null));
            if ($baselineCount >= 6 && $candidateCount < (int) floor($baselineCount * 0.70)) {
                $issues[] = sprintf('%s list shrank too aggressively (%d -> %d)', $field, $baselineCount, $candidateCount);
            }
        }

        $baselineOpen = count($this->normalizeStringList($baseline['open_questions'] ?? null));
        $candidateOpen = count($this->normalizeStringList($candidate['open_questions'] ?? null));
        if ($baselineOpen === 0 && $candidateOpen > 0) {
            $issues[] = 'open_questions reintroduced despite baseline having none';
        }

        if (! $this->revisionMentionsPrivateNotes()) {
            $baselinePrivateNotesLength = mb_strlen(trim((string) ($baseline['private_notes'] ?? '')));
            $candidatePrivateNotesLength = mb_strlen(trim((string) ($candidate['private_notes'] ?? '')));
            if ($baselinePrivateNotesLength >= 800 && $candidatePrivateNotesLength < (int) floor($baselinePrivateNotesLength * 0.70)) {
                $issues[] = sprintf('private_notes shrank too aggressively (%d -> %d)', $baselinePrivateNotesLength, $candidatePrivateNotesLength);
            }
        }

        return [$issues === [], $issues];
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $candidate
     * @param  array<int, string>  $issues
     */
    private function buildRevisionStabilityPrompt(string $basePrompt, array $baseline, array $candidate, array $issues): string
    {
        $issuesText = $issues === [] ? '- output diverged from baseline quality expectations' : '- '.implode("\n- ", $issues);

        return $basePrompt
            ."\n\nRevision quality check failed."
            ."\nProblems detected:\n".$issuesText
            ."\n\nMandatory correction rules:"
            ."\n- Preserve baseline structure and detail unless amendment notes explicitly request removal."
            ."\n- Do not reintroduce open questions that were already resolved."
            ."\n- Keep lists (goals, constraints, acceptance_criteria) comprehensive."
            ."\n- Keep private_notes content unless amendment notes explicitly request changing private notes."
            ."\n\nBaseline summary:\n".$this->summaryContextBlock($baseline)
            ."\n\nRejected revision summary:\n".$this->summaryContextBlock($candidate);
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function preserveUnrequestedRevisionFields(array $baseline, array $candidate): array
    {
        if (! $this->revisionMentionsPrivateNotes()) {
            $baselinePrivateNotes = trim((string) ($baseline['private_notes'] ?? ''));
            if ($baselinePrivateNotes !== '') {
                $candidate['private_notes'] = $baseline['private_notes'];
            }
        }

        return $candidate;
    }

    private function revisionMentionsPrivateNotes(): bool
    {
        $notes = strtolower((string) $this->revisionNotes);
        if ($notes === '') {
            return false;
        }

        return str_contains($notes, 'private notes')
            || str_contains($notes, 'private_notes')
            || str_contains($notes, 'private-notes');
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

    private function runSummaryProcess(InterrogationRunnerAdapter $adapter, InterrogationSession $session, string $summaryPrompt, string $systemPrompt): Process
    {
        $process = new Process(
            $adapter->buildSummaryCommand($session, $summaryPrompt, $systemPrompt),
            (string) $session->project_directory,
            $adapter->buildEnvironment($session),
        );
        $process->setTimeout(600);
        $process->run();

        return $process;
    }

    /**
     * Run adversarial review with full verdict handling.
     *
     * In shadow mode (review_warn_only=true), logs findings to metadata_json
     * and emits events but does not gate artifacts.
     *
     * In gating mode (review_warn_only=false):
     * - pass: returns null (success, continue with summary persistence)
     * - revise: returns review payload for regeneration context
     * - needs_clarification: populates queue and returns halt signal
     *
     * Critical issues auto-escalate pass to revise. Low confidence pass logs
     * warning but does not block. Max retries enforced before failure.
     *
     * @param  array<string, mixed>  $summaryCandidate
     * @return array<string, mixed>|null Null on pass/exhausted; review payload on revise; halt signal on clarification
     */
    private function runAdversarialReview(
        InterrogationSession $session,
        array $summaryCandidate,
        InterrogationEventWriter $writer
    ): ?array {
        $warnOnly = (bool) config('agent.interrogation.review_warn_only', false);
        $maxRetries = (int) config('agent.interrogation.summary_review_max_retries', 3);
        $lowConfidenceThreshold = (float) config('agent.interrogation.review_low_confidence_threshold', 0.6);

        try {
            $reviewerService = app(AdversarialReviewerService::class);
            $reviewPayload = $reviewerService->reviewSummary($session, $summaryCandidate);

            // Store review findings in metadata
            $metadata = $session->metadata_json ?? [];
            $metadata['summary'] = $metadata['summary'] ?? [];
            $attempt = ($metadata['summary']['review_attempts'] ?? 0) + 1;
            $metadata['summary']['review_attempts'] = $attempt;
            $metadata['summary']['last_review'] = $reviewPayload;
            $metadata['summary']['review_history'] = $metadata['summary']['review_history'] ?? [];
            $metadata['summary']['review_history'][] = $reviewPayload;

            $verdict = $reviewPayload['verdict'];
            $confidence = (float) ($reviewPayload['confidence'] ?? 1.0);

            // Check for critical issue auto-escalation
            if ($verdict === 'pass' && $this->hasCriticalIssue($reviewPayload['issues'] ?? [])) {
                $verdict = 'revise';
            }

            // Check low confidence warning (only for pass verdict after escalation check)
            if ($verdict === 'pass' && $confidence < $lowConfidenceThreshold) {
                $metadata['summary']['low_confidence_warning'] = true;
            }

            // Emit review event
            $writer->appendSummaryReview([
                'status' => $warnOnly ? 'shadow_'.$verdict : $verdict,
                'verdict' => $verdict,
                'attempt' => $attempt,
                'issue_count' => count($reviewPayload['issues'] ?? []),
                'confidence' => $confidence,
                'shadow_mode' => $warnOnly,
            ]);

            // Shadow mode: store but don't gate
            if ($warnOnly) {
                $metadata['summary']['review_status'] = 'shadow_'.$verdict;
                $session->update(['metadata_json' => $metadata]);

                return null;
            }

            // Handle verdict
            switch ($verdict) {
                case 'pass':
                    $metadata['summary']['review_status'] = 'passed';
                    $session->update(['metadata_json' => $metadata]);

                    return null; // Success, continue with summary persistence

                case 'revise':
                    if ($attempt >= $maxRetries) {
                        $metadata['summary']['review_status'] = 'accepted_with_warnings';
                        $session->update(['metadata_json' => $metadata]);

                        $writer->appendSystem([
                            'notice' => 'summary_review_exhausted',
                            'message' => 'Adversarial review exhausted retries ('.$maxRetries.'). Accepting current summary with warnings.',
                            'issues' => $reviewPayload['issues'] ?? [],
                            'required_changes' => $reviewPayload['required_changes'] ?? [],
                            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                        ]);

                        return null;
                    }
                    $metadata['summary']['review_status'] = 'revising';
                    $session->update(['metadata_json' => $metadata]);

                    // Return required_changes for regeneration
                    return $reviewPayload;

                case 'needs_clarification':
                    $questions = array_slice($reviewPayload['clarification_questions'] ?? [], 0, 3);
                    $this->insertClarificationQuestions($session, $questions, $metadata);
                    $metadata['summary']['review_status'] = 'clarification_needed';
                    $session->update(['metadata_json' => $metadata]);

                    return ['halt' => true, 'reason' => 'clarification_needed'];
            }

        } catch (\Throwable $e) {
            // In shadow mode, log error but don't fail the job
            if ($warnOnly) {
                report($e);

                // Store error in metadata for debugging
                $metadata = $session->metadata_json ?? [];
                $metadata['summary'] = $metadata['summary'] ?? [];
                $metadata['summary']['review_error'] = [
                    'message' => $e->getMessage(),
                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ];
                $session->update(['metadata_json' => $metadata]);

                return null;
            }

            throw $e;
        }

        return null;
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

    /**
     * Insert clarification questions at front of summary open question queue.
     *
     * @param  array<int, string>  $questions
     * @param  array<string, mixed>  &$metadata
     */
    private function insertClarificationQuestions(InterrogationSession $session, array $questions, array &$metadata): void
    {
        // Insert at front of queue (high priority)
        $queue = $metadata['summary_open_question_queue'] ?? ['questions' => [], 'active' => false];
        $newQuestions = array_map(fn ($q) => ['text' => $q, 'source' => 'reviewer'], $questions);
        $queue['questions'] = array_merge($newQuestions, $queue['questions'] ?? []);
        $queue['active'] = true;
        $metadata['summary_open_question_queue'] = $queue;
    }
}
