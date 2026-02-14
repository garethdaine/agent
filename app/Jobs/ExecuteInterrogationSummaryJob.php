<?php

namespace App\Jobs;

use App\Models\InterrogationSession;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\SessionStateTransitionService;
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

    public function __construct(public int $sessionId)
    {
        $this->onConnection('redis');
        $this->onQueue('interrogation');
    }

    public function handle(
        AdapterFactory $adapterFactory,
        SessionStateTransitionService $transitions,
        SystemPromptResolver $promptResolver,
    ): void {
        $session = InterrogationSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true)) {
            return;
        }

        if ((int) $session->phase !== InterrogationSession::PHASE_SUMMARY) {
            $transitions->transitionPhase(
                (int) $session->id,
                (int) $session->phase,
                InterrogationSession::PHASE_SUMMARY,
                InterrogationSession::STATUS_SUMMARIZING,
                InterrogationSession::ACTIVE_STATUSES,
            );
            $session->refresh();
        }

        $writer = new InterrogationEventWriter($session);

        try {
            $adapter = $adapterFactory->make((string) $session->runner_type);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'summary');
            $summaryPrompt = 'Produce a structured summary JSON for this discovery session. Include summary_markdown, goals, constraints, acceptance_criteria, and open_questions.';

            $process = new Process(
                $adapter->buildSummaryCommand($session, $summaryPrompt, $systemPrompt),
                (string) $session->project_directory,
                $adapter->buildEnvironment($session),
            );
            $process->setTimeout(600);
            $process->run();

            if ($process->getExitCode() !== 0) {
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'SUMMARY_COMMAND_FAILED',
                        'error_summary' => trim($process->getErrorOutput()) ?: 'Summary command failed.',
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'SUMMARY_COMMAND_FAILED',
                    'message' => trim($process->getErrorOutput()) ?: 'Summary command failed.',
                ]);

                return;
            }

            $summary = $adapter->parseSummaryResponse((string) $process->getOutput());

            if ($summary === null) {
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'SUMMARY_PARSE_FAILED',
                        'error_summary' => 'Summary response could not be parsed.',
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'SUMMARY_PARSE_FAILED',
                    'message' => 'Summary response could not be parsed.',
                ]);

                return;
            }

            $metadata = (array) ($session->metadata_json ?? []);
            $metadata['summary_ready'] = true;
            $metadata['summary_generated_at'] = CarbonImmutable::now('UTC')->toIso8601String();

            $session->summary_json = $summary;
            $session->metadata_json = $metadata;
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
}
