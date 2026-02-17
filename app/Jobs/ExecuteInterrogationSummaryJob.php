<?php

namespace App\Jobs;

use App\Models\InterrogationSession;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\Contracts\InterrogationRunnerAdapter;
use App\Support\Interrogation\ConversationReconstructor;
use App\Support\Interrogation\InterrogationEventWriter;
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

        try {
            $adapter = $adapterFactory->make((string) $session->runner_type);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'summary');
            $history = $reconstructor->reconstruct($session);
            $summaryPrompt = 'Produce a structured summary JSON for this discovery session. '
                .'Populate ALL schema fields exactly: summary_markdown, goals, constraints, acceptance_criteria, open_questions, private_notes. '
                .'Return arrays as arrays of plain strings (never embed them inside summary_markdown). '
                .'Do not emit XML-like parameter tags such as <parameter ...>. '
                .'Make the summary implementation-ready and comprehensive (not abbreviated): include concrete decisions, entities, services, config values, and acceptance criteria. '
                .'Do not include estimates or timelines (no days/weeks/months, ETA, total effort, critical path, or parallelization schedule).';

            if (is_string($this->revisionNotes) && trim($this->revisionNotes) !== '') {
                $summaryPrompt = 'Revise the structured summary JSON for this discovery session using the following user amendment notes. '
                    .'Keep the same schema fields (summary_markdown, goals, constraints, acceptance_criteria, open_questions, private_notes). '
                    .'Return arrays as real JSON arrays of strings, never embedded inside summary_markdown. '
                    .'Do not emit XML-like parameter tags such as <parameter ...>. '
                    .'Keep full implementation detail (decisions, entities, services, config, acceptance criteria); do not collapse to high-level prose. '
                    .'Do not include estimates or timelines (no days/weeks/months, ETA, total effort, critical path, or parallelization schedule). '
                    .'Only keep items in open_questions that are truly unresolved after applying the notes.'."\n\n"
                    .'Amendment notes:'."\n".trim($this->revisionNotes);
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
            $writer->appendSystem([
                'notice' => 'summary_ready',
                'message' => 'Summary generated. Awaiting user confirmation.',
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

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
}
