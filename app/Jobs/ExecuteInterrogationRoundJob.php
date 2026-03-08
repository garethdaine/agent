<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\ConversationReconstructor;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\InterrogationQuestionBankGenerator;
use App\Support\Interrogation\InterrogationQuestionBankPlanner;
use App\Support\Interrogation\InterrogationSemanticDeduper;
use App\Support\Interrogation\QuestionPayloadGuard;
use App\Support\Interrogation\SessionStateTransitionService;
use App\Support\Interrogation\SystemPromptResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\Process\Process;

class ExecuteInterrogationRoundJob implements ShouldQueue
{
    use Queueable;

    private const DUPLICATE_TEXT_SIMILARITY_THRESHOLD = 68.0;

    private const DUPLICATE_TOPIC_TEXT_SIMILARITY_THRESHOLD = 45.0;

    private const DUPLICATE_TOPIC_OPTION_SIMILARITY_THRESHOLD = 42.0;

    private const DUPLICATE_SELECTED_OPTION_SIMILARITY_THRESHOLD = 70.0;

    private const DUPLICATE_TOPIC_OVERLAP_MIN = 2;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public int $sessionId,
        public string $userMessage,
        public bool $isSystemMessage = false,
        /** @var array<string, mixed>|null */
        public ?array $answerPayload = null,
        public int $duplicateRecoveryDepth = 0,
    ) {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [2, 4, 8];
    }

    public function handle(
        AdapterFactory $adapterFactory,
        SessionStateTransitionService $transitions,
        SystemPromptResolver $promptResolver,
        ConversationReconstructor $reconstructor,
        QuestionPayloadGuard $questionPayloadGuard,
        InterrogationQuestionBankGenerator $questionBankGenerator,
        InterrogationQuestionBankPlanner $questionBankPlanner,
        InterrogationSemanticDeduper $semanticDeduper,
    ): void {
        $session = InterrogationSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true)) {
            return;
        }

        if ((int) $session->phase !== InterrogationSession::PHASE_INTERROGATION) {
            return;
        }

        $writer = new InterrogationEventWriter($session);

        if (! $this->isSystemMessage && trim($this->userMessage) !== '') {
            $payload = is_array($this->answerPayload) ? $this->answerPayload : [];
            $answerType = (string) ($payload['answer_type'] ?? 'freetext');
            $questionId = isset($payload['question_id']) ? trim((string) $payload['question_id']) : '';
            $canonicalKey = isset($payload['canonical_key']) && is_string($payload['canonical_key'])
                ? trim((string) $payload['canonical_key'])
                : '';

            if ($canonicalKey === '' && $questionId !== '') {
                $canonicalKey = (string) ($this->resolveCanonicalKeyForQuestionId($session, $questionId) ?? '');
            }

            $normalized = [
                'question_id' => $questionId !== '' ? $questionId : null,
                'canonical_key' => $canonicalKey !== '' ? $canonicalKey : null,
                'answer_type' => $answerType,
                'answer_text' => (string) ($payload['answer_text'] ?? ''),
                'selected_option' => (string) ($payload['selected_option'] ?? ''),
                'selected_options' => array_values(array_filter(
                    (array) ($payload['selected_options'] ?? []),
                    static fn ($value): bool => is_string($value) && trim($value) !== ''
                )),
                'skip_reason' => (string) ($payload['skip_reason'] ?? ''),
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];

            if (($normalized['answer_text'] ?? '') === '' && $answerType === 'freetext') { // @phpstan-ignore nullCoalesce.offset
                $normalized['answer_text'] = trim($this->userMessage);
            }

            $writer->appendAnswer($normalized);
            $this->recordAnsweredCanonicalKey($session, $normalized);
        }

        try {
            if ($this->shouldUseDynamicQuestionBank($session)) {
                $handled = $this->handleDynamicQuestionBankRound(
                    session: $session,
                    writer: $writer,
                    adapterFactory: $adapterFactory,
                    transitions: $transitions,
                    promptResolver: $promptResolver,
                    questionBankGenerator: $questionBankGenerator,
                    questionBankPlanner: $questionBankPlanner,
                    semanticDeduper: $semanticDeduper,
                );

                if ($handled) {
                    return;
                }
            }

            $adapter = $adapterFactory->make((string) $session->runner_type, $session->model);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'interrogation');
            $roundPrompt = $this->buildRoundPromptWithAnsweredContext($session, $this->userMessage, $questionPayloadGuard);
            $questionResult = $this->runAndParseQuestion(
                $adapter->buildQuestionCommand($session, $roundPrompt, $systemPrompt),
                (string) $session->project_directory,
                $adapter->buildEnvironment($session),
                fn (string $output) => $adapter->parseQuestionResponse($output),
            );
            $retriedWithoutResume = false;

            if ($this->shouldRetryWithoutResume($session, $questionResult)) {
                $freshSession = clone $session;
                $freshSession->cli_session_id = null;

                $questionResult = $this->runAndParseQuestion(
                    $adapter->buildQuestionCommand($freshSession, $roundPrompt, $systemPrompt),
                    (string) $session->project_directory,
                    $adapter->buildEnvironment($freshSession),
                    fn (string $output) => $adapter->parseQuestionResponse($output),
                );

                $retriedWithoutResume = true;
            }

            if ($questionResult['exit_code'] !== 0 || $questionResult['parsed'] === null) {
                $history = $reconstructor->reconstruct($session);
                $reconstructed = $this->runAndParseQuestion(
                    $adapter->buildReconstructCommand($session, $history, $systemPrompt),
                    (string) $session->project_directory,
                    $adapter->buildEnvironment($session),
                    fn (string $output) => $adapter->parseQuestionResponse($output),
                );

                if ($reconstructed['parsed'] !== null) {
                    $questionResult = $reconstructed;
                }
            }

            if ($retriedWithoutResume && is_string($session->cli_session_id) && trim($session->cli_session_id) !== '') {
                $session->cli_session_id = null;
                $session->save();
            }

            if ($questionResult['parsed'] === null) {
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'ROUND_RESPONSE_PARSE_FAILED',
                        'error_summary' => trim((string) ($questionResult['stderr'] ?? '')) ?: 'Could not parse interrogation round response.', // @phpstan-ignore nullCoalesce.offset
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'ROUND_RESPONSE_PARSE_FAILED',
                    'message' => trim((string) ($questionResult['stderr'] ?? '')) ?: 'Could not parse interrogation round response.', // @phpstan-ignore nullCoalesce.offset
                ]);

                return;
            }

            /** @var array<string, mixed> $question */
            $question = $questionResult['parsed'];

            $validation = $questionPayloadGuard->validate($question);
            if (! $validation['valid']) {
                $repairPrompt = 'Your previous response violated the interrogation contract: '.$validation['reason'].".\n"
                    .'Re-issue the same next-question intent in correct format. '
                    .'Return exactly one question only. Never batch multiple questions. '
                    .'If this is a choice question, set answer_type="choice" and provide options[]; do not embed option text in question_text. '
                    .'Do not return process-status narration about resuming, loading, locating, or workspace/session state.';

                $repaired = $this->runAndParseQuestion(
                    $adapter->buildQuestionCommand($session, $repairPrompt, $systemPrompt),
                    (string) $session->project_directory,
                    $adapter->buildEnvironment($session),
                    fn (string $output) => $adapter->parseQuestionResponse($output),
                );

                if ($repaired['parsed'] !== null && $questionPayloadGuard->validate($repaired['parsed'])['valid']) {
                    $questionResult = $repaired;
                    $question = $questionResult['parsed'];
                } else {
                    $transitions->transition(
                        (int) $session->id,
                        InterrogationSession::ACTIVE_STATUSES,
                        InterrogationSession::STATUS_FAILED,
                        [
                            'error_code' => 'ROUND_CONTRACT_VIOLATION',
                            'error_summary' => 'Runner returned batched/invalid interrogation question payload.',
                            'finished_at' => CarbonImmutable::now('UTC'),
                        ],
                    );

                    $session->refresh();
                    $writer = new InterrogationEventWriter($session);
                    $writer->appendError([
                        'code' => 'ROUND_CONTRACT_VIOLATION',
                        'message' => 'Runner returned batched/invalid interrogation question payload.',
                        'details' => ['reason' => $validation['reason']],
                    ]);

                    return;
                }
            }

            if (isset($question['cli_session_id']) && is_string($question['cli_session_id']) && $question['cli_session_id'] !== '' && $session->cli_session_id !== $question['cli_session_id']) {
                $session->cli_session_id = $question['cli_session_id'];
                $session->save();
            }

            $done = ((int) ($question['progress_estimate'] ?? 0) >= 100)
                || ((bool) ($question['is_complete'] ?? false) === true);

            $substantiveAnswerCount = $this->substantiveAnsweredQuestionCount($session, $questionPayloadGuard);
            $minimumAnswerCount = $this->minimumAnsweredQuestionsForCompletion($session);

            if ($done && ! $this->canAcceptCompletion($session, $questionPayloadGuard)) {
                $completionRepairPrompt = 'Do not mark interrogation complete yet. '
                    .'At least '.$minimumAnswerCount.' substantive answered question(s) are required before completion '
                    .'for this runner/type; currently resolved='.$substantiveAnswerCount.'. '
                    .'Ask exactly one concrete, high-signal next question now. '
                    .'Return is_complete=false and progress_estimate<100.';

                $repaired = $this->runAndParseQuestion(
                    $adapter->buildQuestionCommand($session, $completionRepairPrompt, $systemPrompt),
                    (string) $session->project_directory,
                    $adapter->buildEnvironment($session),
                    fn (string $output) => $adapter->parseQuestionResponse($output),
                );

                if ($repaired['parsed'] !== null) {
                    $repairValidation = $questionPayloadGuard->validate($repaired['parsed']);
                    $repairDone = ((int) ($repaired['parsed']['progress_estimate'] ?? 0) >= 100)
                        || ((bool) ($repaired['parsed']['is_complete'] ?? false) === true);

                    if ($repairValidation['valid'] && ! $repairDone) {
                        $questionResult = $repaired;
                        $question = $questionResult['parsed'];
                        $done = false;
                    }
                }

                if ($done) {
                    $transitions->transition(
                        (int) $session->id,
                        InterrogationSession::ACTIVE_STATUSES,
                        InterrogationSession::STATUS_FAILED,
                        [
                            'error_code' => 'ROUND_CONTRACT_VIOLATION',
                            'error_summary' => 'Runner attempted to complete interrogation before minimum substantive answered question threshold.',
                            'finished_at' => CarbonImmutable::now('UTC'),
                        ],
                    );

                    $session->refresh();
                    $writer = new InterrogationEventWriter($session);
                    $writer->appendError([
                        'code' => 'ROUND_CONTRACT_VIOLATION',
                        'message' => 'Runner attempted to complete interrogation before minimum substantive answered question threshold.',
                    ]);

                    return;
                }
            }

            $clearedResumeSessionForDuplicateRepair = false;
            if (! $done) {
                $duplicateContext = $this->detectDuplicateAnsweredQuestion($session, $question, $questionPayloadGuard);

                if ($duplicateContext !== null) {
                    $duplicateRepairPrompt = $this->buildDuplicateRepairPrompt($duplicateContext);
                    $duplicateResolved = false;

                    $repaired = $this->runAndParseQuestion(
                        $adapter->buildQuestionCommand($session, $duplicateRepairPrompt, $systemPrompt),
                        (string) $session->project_directory,
                        $adapter->buildEnvironment($session),
                        fn (string $output) => $adapter->parseQuestionResponse($output),
                    );

                    if ($repaired['parsed'] !== null) {
                        $repairValidation = $questionPayloadGuard->validate($repaired['parsed']);
                        $repairDuplicateContext = $repairValidation['valid']
                            ? $this->detectDuplicateAnsweredQuestion($session, $repaired['parsed'], $questionPayloadGuard)
                            : ['reason' => (string) ($repairValidation['reason'] ?? 'invalid payload')]; // @phpstan-ignore nullCoalesce.offset

                        if ($repairValidation['valid'] && $repairDuplicateContext === null) {
                            $questionResult = $repaired;
                            $question = $questionResult['parsed'];
                            $done = ((int) ($question['progress_estimate'] ?? 0) >= 100)
                                || ((bool) ($question['is_complete'] ?? false) === true);
                            $duplicateResolved = true;
                        }
                    }

                    if (! $duplicateResolved && is_string($session->cli_session_id) && trim($session->cli_session_id) !== '') {
                        $freshSession = clone $session;
                        $freshSession->cli_session_id = null;

                        $repairedWithoutResume = $this->runAndParseQuestion(
                            $adapter->buildQuestionCommand($freshSession, $duplicateRepairPrompt, $systemPrompt),
                            (string) $session->project_directory,
                            $adapter->buildEnvironment($freshSession),
                            fn (string $output) => $adapter->parseQuestionResponse($output),
                        );

                        if ($repairedWithoutResume['parsed'] !== null) {
                            $repairValidation = $questionPayloadGuard->validate($repairedWithoutResume['parsed']);
                            $repairDuplicateContext = $repairValidation['valid']
                                ? $this->detectDuplicateAnsweredQuestion($session, $repairedWithoutResume['parsed'], $questionPayloadGuard)
                                : ['reason' => (string) ($repairValidation['reason'] ?? 'invalid payload')]; // @phpstan-ignore nullCoalesce.offset

                            if ($repairValidation['valid'] && $repairDuplicateContext === null) {
                                $questionResult = $repairedWithoutResume;
                                $question = $questionResult['parsed'];
                                $done = ((int) ($question['progress_estimate'] ?? 0) >= 100)
                                    || ((bool) ($question['is_complete'] ?? false) === true);
                                $duplicateResolved = true;
                                $clearedResumeSessionForDuplicateRepair = true;
                            }
                        }
                    }

                    if (! $duplicateResolved) {
                        $freshSession = clone $session;
                        $freshSession->cli_session_id = null;
                        $recoveryPrompt = $this->buildDuplicateRecoveryPrompt($session, $questionPayloadGuard, $duplicateContext);

                        $recoveryResult = $this->runAndParseQuestion(
                            $adapter->buildQuestionCommand($freshSession, $recoveryPrompt, $systemPrompt),
                            (string) $session->project_directory,
                            $adapter->buildEnvironment($freshSession),
                            fn (string $output) => $adapter->parseQuestionResponse($output),
                        );

                        if ($recoveryResult['parsed'] !== null) {
                            $recoveryValidation = $questionPayloadGuard->validate($recoveryResult['parsed']);
                            if ($recoveryValidation['valid']) {
                                $questionResult = $recoveryResult;
                                $question = $questionResult['parsed'];
                                $done = ((int) ($question['progress_estimate'] ?? 0) >= 100)
                                    || ((bool) ($question['is_complete'] ?? false) === true);
                                $clearedResumeSessionForDuplicateRepair = true;

                                $recoveryDuplicateContext = $this->detectDuplicateAnsweredQuestion($session, $question, $questionPayloadGuard);
                                if ($recoveryDuplicateContext !== null) {
                                    $writer->appendSystem([
                                        'notice' => 'duplicate_question_warning',
                                        'message' => 'A repeated decision topic was detected. Continuing interrogation with refreshed context.',
                                        'details' => $recoveryDuplicateContext,
                                        'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                                    ]);
                                }
                            } else {
                                $writer->appendSystem([
                                    'notice' => 'duplicate_question_warning',
                                    'message' => 'A repeated decision topic was detected. Continuing interrogation with the latest question.',
                                    'details' => $duplicateContext,
                                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                                ]);
                            }
                        } else {
                            $writer->appendSystem([
                                'notice' => 'duplicate_question_warning',
                                'message' => 'A repeated decision topic was detected. Continuing interrogation with the latest question.',
                                'details' => $duplicateContext,
                                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                            ]);
                        }

                        $recoveryDuplicateContext = $this->detectDuplicateAnsweredQuestion($session, $question, $questionPayloadGuard);
                        if ($recoveryDuplicateContext !== null) {
                            $maxDepth = max(1, (int) config('agent.interrogation.duplicate_recovery_max_depth', 4));
                            $priorAnswerPayload = is_array($recoveryDuplicateContext['previous_answer_payload'] ?? null)
                                ? $recoveryDuplicateContext['previous_answer_payload']
                                : (is_array($duplicateContext['previous_answer_payload'] ?? null)
                                    ? $duplicateContext['previous_answer_payload']
                                    : null);

                            if ($priorAnswerPayload !== null && $this->duplicateRecoveryDepth < $maxDepth) {
                                $autoAnswer = $this->cloneAnswerPayloadForDuplicate($question, $priorAnswerPayload);
                                $writer->appendAnswer($autoAnswer);
                                $writer->appendSystem([
                                    'notice' => 'duplicate_question_auto_resolved',
                                    'message' => 'A repeated question was auto-resolved from a previously confirmed answer.',
                                    'details' => [
                                        'duplicate_question_id' => (string) ($question['question_id'] ?? ''),
                                        'source_question_id' => (string) ($recoveryDuplicateContext['previous_question_id'] ?? ''),
                                        'depth' => $this->duplicateRecoveryDepth + 1,
                                    ],
                                    'at' => CarbonImmutable::now('UTC')->toIso8601String(),
                                ]);

                                ExecuteInterrogationRoundJob::dispatch(
                                    (int) $session->id,
                                    'A duplicate question was auto-resolved from prior decisions. Ask the next single unresolved question only.',
                                    true,
                                    null,
                                    $this->duplicateRecoveryDepth + 1,
                                );

                                if (is_string($session->cli_session_id) && trim($session->cli_session_id) !== '') {
                                    $session->cli_session_id = null;
                                    $session->save();
                                }

                                return;
                            }

                            if ($this->canAcceptCompletion($session, $questionPayloadGuard)) {
                                $writer->appendSystem([
                                    'notice' => 'interrogation_complete',
                                    'message' => 'Interrogation advanced to summary after repeated duplicate questions with no new unresolved decisions.',
                                ]);

                                $moved = $transitions->transitionPhase(
                                    (int) $session->id,
                                    InterrogationSession::PHASE_INTERROGATION,
                                    InterrogationSession::PHASE_SUMMARY,
                                    InterrogationSession::STATUS_SUMMARIZING,
                                    [InterrogationSession::STATUS_INTERROGATING],
                                );

                                if ($moved) {
                                    $session->refresh();
                                    ExecuteInterrogationSummaryJob::dispatch((int) $session->id);
                                }

                                return;
                            }
                        }
                    }
                }
            }

            if ($clearedResumeSessionForDuplicateRepair && is_string($session->cli_session_id) && trim($session->cli_session_id) !== '') {
                $session->cli_session_id = null;
                $session->save();
            }

            if (! $done) {
                $this->recordAskedCanonicalKey($session, $question);
                $writer->appendQuestion($question);

                if ($session->status !== InterrogationSession::STATUS_INTERROGATING) {
                    $transitions->transition(
                        (int) $session->id,
                        [InterrogationSession::STATUS_DISCOVERING, InterrogationSession::STATUS_SUMMARIZING, InterrogationSession::STATUS_PAUSED],
                        InterrogationSession::STATUS_INTERROGATING,
                        ['phase' => InterrogationSession::PHASE_INTERROGATION],
                    );
                }

                return;
            }

            $completionMessage = trim((string) ($question['question_text'] ?? ''));
            if ($completionMessage !== '') {
                $writer->appendSystem([
                    'notice' => 'interrogation_complete',
                    'message' => $completionMessage,
                ]);
            }

            $moved = $transitions->transitionPhase(
                (int) $session->id,
                InterrogationSession::PHASE_INTERROGATION,
                InterrogationSession::PHASE_SUMMARY,
                InterrogationSession::STATUS_SUMMARIZING,
                [InterrogationSession::STATUS_INTERROGATING],
            );

            if (! $moved) {
                return;
            }

            $session->refresh();
            $writer = new InterrogationEventWriter($session);
            $writer->appendPhaseTransition(
                InterrogationSession::PHASE_INTERROGATION,
                InterrogationSession::PHASE_SUMMARY,
                (string) $session->status,
                ['at' => CarbonImmutable::now('UTC')->toIso8601String()]
            );

            ExecuteInterrogationSummaryJob::dispatch((int) $session->id);
        } catch (\Throwable $throwable) {
            report($throwable);

            $transitions->transition(
                (int) $session->id,
                InterrogationSession::ACTIVE_STATUSES,
                InterrogationSession::STATUS_FAILED,
                [
                    'error_code' => 'ROUND_RUNTIME_EXCEPTION',
                    'error_summary' => $throwable->getMessage(),
                    'finished_at' => CarbonImmutable::now('UTC'),
                ],
            );

            $session->refresh();
            $writer = new InterrogationEventWriter($session);
            $writer->appendError([
                'code' => 'ROUND_RUNTIME_EXCEPTION',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string|bool>  $env
     * @param  callable(string):array<string,mixed>|null  $parser
     * @return array{exit_code:int,stdout:string,stderr:string,parsed:array<string,mixed>|null}
     */
    private function runAndParseQuestion(array $command, string $cwd, array $env, callable $parser): array // @phpstan-ignore parameter.phpDocType
    {
        $process = new Process($command, $cwd, $env);
        $process->setTimeout(600);
        $process->run();

        $stdout = (string) $process->getOutput();
        $stderr = (string) $process->getErrorOutput();

        return [
            'exit_code' => (int) ($process->getExitCode() ?? 1),
            'stdout' => $stdout,
            'stderr' => $stderr,
            'parsed' => $parser($stdout),
        ];
    }

    /**
     * @param  array{exit_code:int,stdout:string,stderr:string,parsed:array<string,mixed>|null}  $result
     */
    private function shouldRetryWithoutResume(InterrogationSession $session, array $result): bool
    {
        return is_string($session->cli_session_id)
            && trim($session->cli_session_id) !== ''
            && ((int) ($result['exit_code'] ?? 1) !== 0 || ($result['parsed'] ?? null) === null); // @phpstan-ignore nullCoalesce.offset
    }

    private function canAcceptCompletion(InterrogationSession $session, QuestionPayloadGuard $questionPayloadGuard): bool
    {
        return $this->substantiveAnsweredQuestionCount($session, $questionPayloadGuard)
            >= $this->minimumAnsweredQuestionsForCompletion($session);
    }

    private function substantiveAnsweredQuestionCount(InterrogationSession $session, QuestionPayloadGuard $questionPayloadGuard): int
    {
        $events = $session->events()
            ->orderBy('sequence')
            ->get(['event_type', 'payload']);

        if ($events->isEmpty()) {
            return 0;
        }

        $answerableQuestionIds = [];
        /** @var \App\Models\InterrogationEvent $event */
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_QUESTION) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : []; // @phpstan-ignore function.alreadyNarrowedType
            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            $isCompletionMarker = ((int) ($payload['progress_estimate'] ?? 0) >= 100)
                || ((bool) ($payload['is_complete'] ?? false) === true);
            if ($isCompletionMarker) {
                continue;
            }

            if (! ($questionPayloadGuard->validate($payload)['valid'] ?? false)) { // @phpstan-ignore nullCoalesce.offset
                continue;
            }

            $answerableQuestionIds[$questionId] = true;
        }

        if ($answerableQuestionIds === []) {
            return 0;
        }

        $answeredQuestionIds = [];

        /** @var \App\Models\InterrogationEvent $event */
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_ANSWER) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : []; // @phpstan-ignore function.alreadyNarrowedType
            $answerType = strtolower(trim((string) ($payload['answer_type'] ?? '')));
            if ($answerType === 'skip') {
                continue;
            }

            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            if (! isset($answerableQuestionIds[$questionId])) {
                continue;
            }

            $answerText = trim((string) ($payload['answer_text'] ?? ''));
            $selectedOption = trim((string) ($payload['selected_option'] ?? ''));
            $selectedOptions = array_filter(
                (array) ($payload['selected_options'] ?? []),
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            );

            if ($answerText === '' && $selectedOption === '' && $selectedOptions === []) {
                continue;
            }

            $answeredQuestionIds[$questionId] = true;
        }

        return count($answeredQuestionIds);
    }

    private function minimumAnsweredQuestionsForCompletion(InterrogationSession $session): int
    {
        if ((string) $session->runner_type !== 'codex') {
            return 1;
        }

        $configKey = $session->interrogation_type === InterrogationSession::TYPE_FEATURE
            ? 'agent.interrogation.codex_min_feature_answers'
            : 'agent.interrogation.codex_min_general_answers';

        return max(1, (int) config($configKey, 1));
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>|null
     */
    private function detectDuplicateAnsweredQuestion(
        InterrogationSession $session,
        array $candidate,
        QuestionPayloadGuard $questionPayloadGuard,
    ): ?array {
        $isCompletionMarker = ((int) ($candidate['progress_estimate'] ?? 0) >= 100)
            || ((bool) ($candidate['is_complete'] ?? false) === true);
        if ($isCompletionMarker) {
            return null;
        }

        $candidateQuestionId = trim((string) ($candidate['question_id'] ?? ''));
        $candidateText = trim((string) ($candidate['question_text'] ?? ''));
        if ($candidateText === '') {
            return null;
        }

        $candidateNormalizedText = $this->normalizeComparableText($candidateText);
        $candidateOptions = $this->normalizeComparableOptions((array) ($candidate['options'] ?? []));
        $candidateTopics = $this->extractIntentTopics($candidateText, $candidateOptions);

        $answeredSnapshots = $this->answeredQuestionSnapshots($session, $questionPayloadGuard);

        foreach ($answeredSnapshots as $snapshot) {
            $questionPayload = $snapshot['question'];
            $answerPayload = $snapshot['answer'];

            $priorQuestionId = trim((string) ($questionPayload['question_id'] ?? ''));
            $priorQuestionText = trim((string) ($questionPayload['question_text'] ?? ''));
            if ($priorQuestionText === '') {
                continue;
            }

            if ($candidateQuestionId !== '' && $priorQuestionId !== '' && $candidateQuestionId === $priorQuestionId) {
                return [
                    'reason' => 'question_id already answered',
                    'previous_question_id' => $priorQuestionId,
                    'previous_question_text' => $priorQuestionText,
                    'previous_answer' => $this->summarizeAnswerPayload($answerPayload),
                    'previous_answer_payload' => $answerPayload,
                ];
            }

            $priorNormalizedText = $this->normalizeComparableText($priorQuestionText);
            $textSimilarity = $this->similarityPercent($candidateNormalizedText, $priorNormalizedText);

            if ($candidateNormalizedText !== '' && $candidateNormalizedText === $priorNormalizedText) {
                return [
                    'reason' => 'question_text is an exact repeat of an answered question',
                    'previous_question_id' => $priorQuestionId,
                    'previous_question_text' => $priorQuestionText,
                    'previous_answer' => $this->summarizeAnswerPayload($answerPayload),
                    'previous_answer_payload' => $answerPayload,
                    'text_similarity' => $textSimilarity,
                ];
            }

            if ($textSimilarity >= self::DUPLICATE_TEXT_SIMILARITY_THRESHOLD) {
                return [
                    'reason' => 'question_text is a semantic rephrase of an answered question',
                    'previous_question_id' => $priorQuestionId,
                    'previous_question_text' => $priorQuestionText,
                    'previous_answer' => $this->summarizeAnswerPayload($answerPayload),
                    'previous_answer_payload' => $answerPayload,
                    'text_similarity' => $textSimilarity,
                ];
            }

            $priorOptions = $this->normalizeComparableOptions((array) ($questionPayload['options'] ?? []));
            $optionSimilarity = $this->maxSimilarityAcrossLists($candidateOptions, $priorOptions);
            $selectedOptionSimilarity = $this->maxAnswerChoiceSimilarityToOptions($answerPayload, $candidateOptions);

            if ($selectedOptionSimilarity >= self::DUPLICATE_SELECTED_OPTION_SIMILARITY_THRESHOLD) {
                return [
                    'reason' => 'candidate options repeat an already-selected answer',
                    'previous_question_id' => $priorQuestionId,
                    'previous_question_text' => $priorQuestionText,
                    'previous_answer' => $this->summarizeAnswerPayload($answerPayload),
                    'previous_answer_payload' => $answerPayload,
                    'selected_option_similarity' => $selectedOptionSimilarity,
                ];
            }

            $priorTopics = $this->extractIntentTopics($priorQuestionText, $priorOptions);
            $sharedTopics = array_values(array_intersect($candidateTopics, $priorTopics));

            if (count($sharedTopics) >= self::DUPLICATE_TOPIC_OVERLAP_MIN
                && ($textSimilarity >= self::DUPLICATE_TOPIC_TEXT_SIMILARITY_THRESHOLD
                    || $optionSimilarity >= self::DUPLICATE_TOPIC_OPTION_SIMILARITY_THRESHOLD)
            ) {
                return [
                    'reason' => 'candidate repeats an already-resolved decision topic',
                    'previous_question_id' => $priorQuestionId,
                    'previous_question_text' => $priorQuestionText,
                    'previous_answer' => $this->summarizeAnswerPayload($answerPayload),
                    'previous_answer_payload' => $answerPayload,
                    'shared_topics' => $sharedTopics,
                    'text_similarity' => $textSimilarity,
                    'option_similarity' => $optionSimilarity,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{question:array<string,mixed>,answer:array<string,mixed>}>
     */
    private function answeredQuestionSnapshots(
        InterrogationSession $session,
        QuestionPayloadGuard $questionPayloadGuard,
    ): array {
        $events = $session->events()
            ->orderBy('sequence')
            ->get(['event_type', 'payload']);

        if ($events->isEmpty()) {
            return [];
        }

        $questionsById = [];
        /** @var \App\Models\InterrogationEvent $event */
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_QUESTION) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : []; // @phpstan-ignore function.alreadyNarrowedType
            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            $isCompletionMarker = ((int) ($payload['progress_estimate'] ?? 0) >= 100)
                || ((bool) ($payload['is_complete'] ?? false) === true);
            if ($isCompletionMarker) {
                continue;
            }

            if (! ($questionPayloadGuard->validate($payload)['valid'] ?? false)) { // @phpstan-ignore nullCoalesce.offset
                continue;
            }

            $questionsById[$questionId] = $payload;
        }

        if ($questionsById === []) {
            return [];
        }

        $answersByQuestionId = [];
        /** @var \App\Models\InterrogationEvent $event */
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_ANSWER) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : []; // @phpstan-ignore function.alreadyNarrowedType
            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '' || ! isset($questionsById[$questionId])) {
                continue;
            }

            $answerType = strtolower(trim((string) ($payload['answer_type'] ?? '')));
            if ($answerType === 'skip') {
                continue;
            }

            $answerText = trim((string) ($payload['answer_text'] ?? ''));
            $selectedOption = trim((string) ($payload['selected_option'] ?? ''));
            $selectedOptions = array_values(array_filter(
                (array) ($payload['selected_options'] ?? []),
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            ));

            if ($answerText === '' && $selectedOption === '' && $selectedOptions === []) {
                continue;
            }

            $answersByQuestionId[$questionId] = $payload;
        }

        $snapshots = [];
        foreach ($answersByQuestionId as $questionId => $answerPayload) {
            $questionPayload = $questionsById[$questionId] ?? null;
            if (! is_array($questionPayload)) {
                continue;
            }

            $snapshots[] = [
                'question' => $questionPayload,
                'answer' => $answerPayload,
            ];
        }

        return $snapshots;
    }

    /**
     * @param  array<string, mixed>  $duplicateContext
     */
    private function buildDuplicateRepairPrompt(array $duplicateContext): string
    {
        $previousQuestion = trim((string) ($duplicateContext['previous_question_text'] ?? ''));
        $previousAnswer = trim((string) ($duplicateContext['previous_answer'] ?? ''));
        $sharedTopics = is_array($duplicateContext['shared_topics'] ?? null)
            ? array_values(array_filter($duplicateContext['shared_topics'], static fn ($topic): bool => is_string($topic) && trim($topic) !== ''))
            : [];
        $topicSummary = $sharedTopics === [] ? '' : ' Overlapping topic(s): '.implode(', ', $sharedTopics).'.';

        $prompt = 'Do not repeat already-resolved requirements decisions. '
            .'Your last candidate question overlapped with a question that is already answered.';

        if ($previousQuestion !== '') {
            $prompt .= ' Previously answered question: "'.$previousQuestion.'".';
        }

        if ($previousAnswer !== '') {
            $prompt .= ' Recorded answer: "'.$previousAnswer.'".';
        }

        $prompt .= $topicSummary.' Ask exactly one materially different unresolved question now. '
            .'It must introduce a new decision dimension, not a rephrase of answered visibility/versioning/access choices. '
            .'Return one schema-valid JSON object only.';

        return $prompt;
    }

    /**
     * @param  array<string, mixed>  $answerPayload
     */
    private function summarizeAnswerPayload(array $answerPayload): string
    {
        $selectedOption = trim((string) ($answerPayload['selected_option'] ?? ''));
        if ($selectedOption !== '') {
            return $selectedOption;
        }

        $selectedOptions = array_values(array_filter(
            (array) ($answerPayload['selected_options'] ?? []),
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));
        if ($selectedOptions !== []) {
            return implode(', ', $selectedOptions);
        }

        $answerText = trim((string) ($answerPayload['answer_text'] ?? ''));
        if ($answerText === '') {
            return '';
        }

        if (mb_strlen($answerText) > 180) {
            return rtrim(mb_substr($answerText, 0, 180)).'…';
        }

        return $answerText;
    }

    /**
     * @param  array<int, mixed>  $options
     * @return array<int, string>
     */
    private function normalizeComparableOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $option) {
            if (! is_string($option)) {
                continue;
            }

            $text = $this->normalizeComparableText($option);
            if ($text === '') {
                continue;
            }

            $normalized[] = $text;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeComparableText(string $text): string
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^a-z0-9\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? trim($normalized);

        return $normalized;
    }

    private function similarityPercent(string $left, string $right): float
    {
        $left = trim($left);
        $right = trim($right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);

        return (float) $percent;
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function maxSimilarityAcrossLists(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $max = 0.0;
        foreach ($left as $leftValue) {
            foreach ($right as $rightValue) {
                $max = max($max, $this->similarityPercent($leftValue, $rightValue));
            }
        }

        return $max;
    }

    /**
     * @param  array<string, mixed>  $answerPayload
     * @param  array<int, string>  $candidateOptions
     */
    private function maxAnswerChoiceSimilarityToOptions(array $answerPayload, array $candidateOptions): float
    {
        if ($candidateOptions === []) {
            return 0.0;
        }

        $choices = [];
        $selectedOption = trim((string) ($answerPayload['selected_option'] ?? ''));
        if ($selectedOption !== '') {
            $choices[] = $this->normalizeComparableText($selectedOption);
        }

        foreach ((array) ($answerPayload['selected_options'] ?? []) as $selected) {
            if (! is_string($selected)) {
                continue;
            }

            $normalized = $this->normalizeComparableText($selected);
            if ($normalized !== '') {
                $choices[] = $normalized;
            }
        }

        $choices = array_values(array_unique(array_filter($choices, static fn (string $value): bool => $value !== '')));
        if ($choices === []) {
            return 0.0;
        }

        return $this->maxSimilarityAcrossLists($choices, $candidateOptions);
    }

    /**
     * @param  array<int, string>  $normalizedOptions
     * @return array<int, string>
     */
    private function extractIntentTopics(string $questionText, array $normalizedOptions): array
    {
        $subject = $this->normalizeComparableText($questionText.' '.implode(' ', $normalizedOptions));
        if ($subject === '') {
            return [];
        }

        $patterns = [
            'visibility' => '/\b(visibility|access|public|private)\b/i',
            'auth' => '/\b(auth|authenticated|authentication|permission|permissions|role|roles)\b/i',
            'versioning' => '/\b(version|versioning|versions|snapshot|snapshots|history|revision|latest|live|overwrite|tag|tags|release)\b/i',
            'authoring' => '/\b(author|authoring|edit|editing|create|draft|publish|publisher|writer|maintain)\b/i',
            'docs' => '/\b(doc|docs|documentation|tooltip|tooltips|content|knowledgebase|knowledge)\b/i',
            'api' => '/\bapi\b/i',
            'scope' => '/\b(mvp|phase|release|first)\b/i',
        ];

        $topics = [];
        foreach ($patterns as $topic => $pattern) {
            if (preg_match($pattern, $subject) === 1) {
                $topics[] = $topic;
            }
        }

        return $topics;
    }

    private function buildRoundPromptWithAnsweredContext(
        InterrogationSession $session,
        string $userMessage,
        QuestionPayloadGuard $questionPayloadGuard,
    ): string {
        $snapshots = $this->answeredQuestionSnapshots($session, $questionPayloadGuard);
        if ($snapshots === []) {
            return $userMessage;
        }

        $decisionLines = [];
        foreach (array_slice($snapshots, -8) as $snapshot) {
            $questionPayload = $snapshot['question'];
            $answerPayload = $snapshot['answer'];

            $questionId = trim((string) ($questionPayload['question_id'] ?? ''));
            $questionText = trim((string) ($questionPayload['question_text'] ?? ''));
            $answerSummary = $this->summarizeAnswerPayload($answerPayload);

            if ($questionText === '' || $answerSummary === '') {
                continue;
            }

            if (mb_strlen($questionText) > 140) {
                $questionText = rtrim(mb_substr($questionText, 0, 140)).'…';
            }
            if (mb_strlen($answerSummary) > 100) {
                $answerSummary = rtrim(mb_substr($answerSummary, 0, 100)).'…';
            }

            $prefix = $questionId !== '' ? '['.$questionId.'] ' : '';
            $decisionLines[] = '- '.$prefix.$questionText.' => '.$answerSummary;
        }

        if ($decisionLines === []) {
            return $userMessage;
        }

        return trim($userMessage)."\n\n"
            .'Resolved decisions (do not re-ask these topics):'."\n"
            .implode("\n", $decisionLines)."\n\n"
            .'Ask exactly one materially different unresolved question next. '
            .'Do not rephrase or repeat any resolved decision above.';
    }

    /**
     * @param  array<string, mixed>  $duplicateContext
     */
    private function buildDuplicateRecoveryPrompt(
        InterrogationSession $session,
        QuestionPayloadGuard $questionPayloadGuard,
        array $duplicateContext,
    ): string {
        $base = 'Continue interrogation and ask the next unresolved requirement question only.';
        $base .= ' Do not ask another visibility/versioning/access variant that is already answered.';

        $reason = trim((string) ($duplicateContext['reason'] ?? ''));
        if ($reason !== '') {
            $base .= ' Duplicate reason: '.$reason.'.';
        }

        return $this->buildRoundPromptWithAnsweredContext($session, $base, $questionPayloadGuard);
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $priorAnswerPayload
     * @return array<string, mixed>
     */
    private function cloneAnswerPayloadForDuplicate(array $question, array $priorAnswerPayload): array
    {
        $questionId = trim((string) ($question['question_id'] ?? ''));
        $answerType = strtolower(trim((string) ($priorAnswerPayload['answer_type'] ?? 'freetext')));
        if ($answerType === '' || $answerType === 'skip') {
            $answerType = 'freetext';
        }

        $selectedOptions = array_values(array_filter(
            (array) ($priorAnswerPayload['selected_options'] ?? []),
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));

        return [
            'question_id' => $questionId !== '' ? $questionId : null,
            'canonical_key' => is_string($question['canonical_key'] ?? null) ? $question['canonical_key'] : null,
            'answer_type' => $answerType,
            'answer_text' => (string) ($priorAnswerPayload['answer_text'] ?? ''),
            'selected_option' => (string) ($priorAnswerPayload['selected_option'] ?? ''),
            'selected_options' => $selectedOptions,
            'skip_reason' => '',
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'auto_resolved_duplicate' => true,
            'source_question_id' => (string) ($priorAnswerPayload['question_id'] ?? ''),
        ];
    }

    private function shouldUseDynamicQuestionBank(InterrogationSession $session): bool
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];

        // When the summary open question queue is active, the round job must use
        // the standard AI runner path so the queued open question prompt is sent
        // directly. The dynamic question bank would otherwise auto-complete
        // interrogation because all its canonical keys are already exhausted.
        $queueActive = (bool) data_get($metadata, 'summary_open_question_queue.active', false);
        if ($queueActive) {
            return false;
        }

        $mode = data_get($metadata, 'dynamic_question_bank_mode');

        if (is_bool($mode)) {
            return $mode;
        }

        return false;
    }

    private function resolveCanonicalKeyForQuestionId(InterrogationSession $session, string $questionId): ?string
    {
        $questionId = trim($questionId);
        if ($questionId === '') {
            return null;
        }

        $payload = $session->events()
            ->where('event_type', InterrogationEvent::TYPE_QUESTION)
            ->where('payload->question_id', $questionId)
            ->orderByDesc('sequence')
            ->value('payload');

        if (! is_array($payload)) {
            return null;
        }

        $canonicalKey = trim((string) ($payload['canonical_key'] ?? ''));

        return $canonicalKey === '' ? null : $canonicalKey;
    }

    /**
     * @param  array<string, mixed>  $answerPayload
     */
    private function recordAnsweredCanonicalKey(InterrogationSession $session, array $answerPayload): void
    {
        $canonicalKey = trim((string) ($answerPayload['canonical_key'] ?? ''));
        if ($canonicalKey === '') {
            return;
        }

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['answered_canonical_keys'] = $this->mergeCanonicalKey(
            (array) ($metadata['answered_canonical_keys'] ?? []),
            $canonicalKey,
        );
        $metadata['locked_policy_snapshot'] = is_array($metadata['locked_policy_snapshot'] ?? null)
            ? $metadata['locked_policy_snapshot']
            : [];
        $metadata['locked_policy_snapshot'][$canonicalKey] = $this->summarizeAnswerPayload($answerPayload);
        $session->metadata_json = $metadata;
        $session->save();
    }

    /**
     * @param  array<string, mixed>  $questionPayload
     */
    private function recordAskedCanonicalKey(InterrogationSession $session, array $questionPayload): void
    {
        $canonicalKey = trim((string) ($questionPayload['canonical_key'] ?? ''));
        if ($canonicalKey === '') {
            return;
        }

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['asked_canonical_keys'] = $this->mergeCanonicalKey(
            (array) ($metadata['asked_canonical_keys'] ?? []),
            $canonicalKey,
        );
        $session->metadata_json = $metadata;
        $session->save();
    }

    /**
     * @param  array<int, mixed>  $keys
     * @return array<int, string>
     */
    private function mergeCanonicalKey(array $keys, string $canonicalKey): array
    {
        $normalized = array_values(array_filter(
            array_map(static fn ($value): string => is_string($value) ? trim($value) : '', $keys),
            static fn (string $value): bool => $value !== ''
        ));

        $normalized[] = trim($canonicalKey);

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $value): bool => $value !== ''
        )));
    }

    private function handleDynamicQuestionBankRound(
        InterrogationSession $session,
        InterrogationEventWriter $writer,
        AdapterFactory $adapterFactory,
        SessionStateTransitionService $transitions,
        SystemPromptResolver $promptResolver,
        InterrogationQuestionBankGenerator $questionBankGenerator,
        InterrogationQuestionBankPlanner $questionBankPlanner,
        InterrogationSemanticDeduper $semanticDeduper,
    ): bool {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $questionBank = is_array($metadata['dynamic_question_bank'] ?? null)
            ? $metadata['dynamic_question_bank']
            : [];

        if ($questionBank === []) {
            $adapter = $adapterFactory->make((string) $session->runner_type, $session->model);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'interrogation');
            $generated = $questionBankGenerator->generate($session, $adapter, $systemPrompt);

            if (! is_array($generated) || ! is_array($generated['questions'] ?? null) || $generated['questions'] === []) { // @phpstan-ignore function.alreadyNarrowedType, nullCoalesce.offset
                return false;
            }

            $questionBank = $generated['questions'];
            $metadata['dynamic_question_bank'] = $questionBank;
            $metadata['dynamic_question_bank_generated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

            if (is_string($generated['cli_session_id'] ?? null) && trim((string) $generated['cli_session_id']) !== '') {
                $session->cli_session_id = trim((string) $generated['cli_session_id']);
            }
        }

        $askedCanonicalKeys = array_values(array_filter(
            (array) ($metadata['asked_canonical_keys'] ?? []),
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));
        $answeredCanonicalKeys = array_values(array_filter(
            (array) ($metadata['answered_canonical_keys'] ?? []),
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));
        $suppressedCanonicalKeys = array_values(array_filter(
            (array) ($metadata['suppressed_canonical_keys'] ?? []),
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));
        $askedQuestionTexts = $this->askedQuestionTexts($session);

        $selection = $questionBankPlanner->nextEligibleQuestion(
            questionBank: $questionBank,
            askedCanonicalKeys: $askedCanonicalKeys,
            answeredCanonicalKeys: $answeredCanonicalKeys,
            suppressedCanonicalKeys: $suppressedCanonicalKeys,
            askedQuestionTexts: $askedQuestionTexts,
            deduper: $semanticDeduper,
            threshold: (float) config('agent.interrogation.semantic_dedupe_similarity_threshold', 0.88),
        );

        $newSuppressed = array_values(array_unique(array_merge(
            $suppressedCanonicalKeys,
            (array) ($selection['suppressed'] ?? []) // @phpstan-ignore nullCoalesce.offset
        )));
        $metadata['suppressed_canonical_keys'] = $newSuppressed;

        $question = is_array($selection['question'] ?? null) ? $selection['question'] : null;

        if ($question === null) {
            $session->metadata_json = $metadata;
            $session->save();

            $writer->appendSystem([
                'notice' => 'interrogation_complete',
                'message' => 'Interrogation completed: no eligible unanswered decision axes remain.',
                'at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ]);

            $moved = $transitions->transitionPhase(
                (int) $session->id,
                InterrogationSession::PHASE_INTERROGATION,
                InterrogationSession::PHASE_SUMMARY,
                InterrogationSession::STATUS_SUMMARIZING,
                [InterrogationSession::STATUS_INTERROGATING],
            );

            if ($moved) {
                $session->refresh();
                (new InterrogationEventWriter($session))->appendPhaseTransition(
                    InterrogationSession::PHASE_INTERROGATION,
                    InterrogationSession::PHASE_SUMMARY,
                    (string) $session->status,
                    ['at' => CarbonImmutable::now('UTC')->toIso8601String()],
                );
                ExecuteInterrogationSummaryJob::dispatch((int) $session->id);
            }

            return true;
        }

        $canonicalKey = trim((string) ($question['canonical_key'] ?? ''));
        $totalQuestions = max(1, count($questionBank));
        $nextAskedCount = count(array_unique(array_merge($askedCanonicalKeys, [$canonicalKey])));
        $progressEstimate = max(1, min(99, (int) floor(($nextAskedCount / $totalQuestions) * 99)));

        $questionPayload = [
            'question_id' => (string) ($question['question_id'] ?? $questionBankPlanner->questionIdForCanonicalKey($canonicalKey)),
            'canonical_key' => $canonicalKey !== '' ? $canonicalKey : null,
            'question_text' => (string) ($question['prompt'] ?? ''),
            'answer_type' => (string) ($question['answer_type'] ?? 'choice'),
            'options' => is_array($question['options'] ?? null) ? array_values($question['options']) : [],
            'reasoning' => (string) ($question['rationale'] ?? ''),
            'category' => (string) ($question['category'] ?? 'general'),
            'progress_estimate' => $progressEstimate,
            'is_complete' => false,
        ];

        $metadata['asked_canonical_keys'] = $this->mergeCanonicalKey($askedCanonicalKeys, $canonicalKey);
        $session->metadata_json = $metadata;
        $session->save();

        $writer->appendQuestion($questionPayload);

        if ($session->status !== InterrogationSession::STATUS_INTERROGATING) {
            $transitions->transition(
                (int) $session->id,
                [InterrogationSession::STATUS_DISCOVERING, InterrogationSession::STATUS_SUMMARIZING, InterrogationSession::STATUS_PAUSED],
                InterrogationSession::STATUS_INTERROGATING,
                ['phase' => InterrogationSession::PHASE_INTERROGATION],
            );
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function askedQuestionTexts(InterrogationSession $session): array
    {
        return $session->events()
            ->where('event_type', InterrogationEvent::TYPE_QUESTION)
            ->orderBy('sequence')
            ->get(['payload'])
            ->map(function (InterrogationEvent $event): string {
                $payload = is_array($event->payload) ? $event->payload : []; // @phpstan-ignore function.alreadyNarrowedType

                return trim((string) ($payload['question_text'] ?? ''));
            })
            ->filter(static fn (string $text): bool => $text !== '')
            ->values()
            ->all();
    }
}
