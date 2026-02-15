<?php

namespace App\Jobs;

use App\Models\InterrogationSession;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\ConversationReconstructor;
use App\Support\Interrogation\InterrogationEventWriter;
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
    )
    {
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

            if (isset($question['cli_session_id']) && is_string($question['cli_session_id']) && $question['cli_session_id'] !== '' && $session->cli_session_id !== $question['cli_session_id']) {
                $session->cli_session_id = $question['cli_session_id'];
                $session->save();
            }

            $writer->appendQuestion($question);

            $done = ((int) ($question['progress_estimate'] ?? 0) >= 100)
                || ((bool) ($question['is_complete'] ?? false) === true);

            if (! $done) {
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
     * @param  array<string, string>  $env
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
}
