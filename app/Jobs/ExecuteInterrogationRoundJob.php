<?php

namespace App\Jobs;

use App\Models\InterrogationEvent;
use App\Models\InterrogationSession;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\ConversationReconstructor;
use App\Support\Interrogation\InterrogationEventWriter;
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

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public int $sessionId,
        public string $userMessage,
        public bool $isSystemMessage = false,
        /** @var array<string, mixed>|null */
        public ?array $answerPayload = null,
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
    ): void {
        $session = InterrogationSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true)) {
            return;
        }

        $writer = new InterrogationEventWriter($session);

        if (! $this->isSystemMessage && trim($this->userMessage) !== '') {
            $payload = is_array($this->answerPayload) ? $this->answerPayload : [];
            $answerType = (string) ($payload['answer_type'] ?? 'freetext');
            $questionId = isset($payload['question_id']) ? trim((string) $payload['question_id']) : '';

            $normalized = [
                'question_id' => $questionId !== '' ? $questionId : null,
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

            if (($normalized['answer_text'] ?? '') === '' && $answerType === 'freetext') {
                $normalized['answer_text'] = trim($this->userMessage);
            }

            $writer->appendAnswer($normalized);
        }

        try {
            $adapter = $adapterFactory->make((string) $session->runner_type);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'interrogation');
            $questionResult = $this->runAndParseQuestion(
                $adapter->buildQuestionCommand($session, $this->userMessage, $systemPrompt),
                (string) $session->project_directory,
                $adapter->buildEnvironment($session),
                fn (string $output) => $adapter->parseQuestionResponse($output),
            );
            $retriedWithoutResume = false;

            if ($this->shouldRetryWithoutResume($session, $questionResult)) {
                $freshSession = clone $session;
                $freshSession->cli_session_id = null;

                $questionResult = $this->runAndParseQuestion(
                    $adapter->buildQuestionCommand($freshSession, $this->userMessage, $systemPrompt),
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
                        'error_summary' => trim((string) ($questionResult['stderr'] ?? '')) ?: 'Could not parse interrogation round response.',
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'ROUND_RESPONSE_PARSE_FAILED',
                    'message' => trim((string) ($questionResult['stderr'] ?? '')) ?: 'Could not parse interrogation round response.',
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

            if (! $done) {
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
    private function runAndParseQuestion(array $command, string $cwd, array $env, callable $parser): array
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
            && ((int) ($result['exit_code'] ?? 1) !== 0 || ($result['parsed'] ?? null) === null);
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
        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_QUESTION) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : [];
            $questionId = trim((string) ($payload['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            $isCompletionMarker = ((int) ($payload['progress_estimate'] ?? 0) >= 100)
                || ((bool) ($payload['is_complete'] ?? false) === true);
            if ($isCompletionMarker) {
                continue;
            }

            if (! ($questionPayloadGuard->validate($payload)['valid'] ?? false)) {
                continue;
            }

            $answerableQuestionIds[$questionId] = true;
        }

        if ($answerableQuestionIds === []) {
            return 0;
        }

        $answeredQuestionIds = [];

        foreach ($events as $event) {
            if ($event->event_type !== InterrogationEvent::TYPE_ANSWER) {
                continue;
            }

            $payload = is_array($event->payload) ? $event->payload : [];
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
}
