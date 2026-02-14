<?php

namespace App\Jobs;

use App\Models\InterrogationSession;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\ExportService;
use App\Support\Interrogation\InterrogationEventWriter;
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

    public int $timeout = 900;

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
    ): void {
        $session = InterrogationSession::query()->find($this->sessionId);

        if ($session === null) {
            return;
        }

        if (in_array($session->status, InterrogationSession::TERMINAL_STATUSES, true) && $session->status !== InterrogationSession::STATUS_COMPLETED) {
            return;
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

            $planningPrompt = trim((string) $this->revisionPrompt);
            if ($planningPrompt === '') {
                $planningPrompt = 'Generate an implementation plan JSON with plan_markdown, sections, risks, and assumptions based on the confirmed summary.';
            }

            $process = new Process(
                $adapter->buildPlanCommand($session, $planningPrompt, $systemPrompt),
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
                        'error_code' => 'PLAN_COMMAND_FAILED',
                        'error_summary' => trim($process->getErrorOutput()) ?: 'Plan command failed.',
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'PLAN_COMMAND_FAILED',
                    'message' => trim($process->getErrorOutput()) ?: 'Plan command failed.',
                ]);

                return;
            }

            $plan = $adapter->parsePlanResponse((string) $process->getOutput());

            if ($plan === null) {
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'PLAN_PARSE_FAILED',
                        'error_summary' => 'Plan response could not be parsed.',
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer = new InterrogationEventWriter($session);
                $writer->appendError([
                    'code' => 'PLAN_PARSE_FAILED',
                    'message' => 'Plan response could not be parsed.',
                ]);

                return;
            }

            $session->plan_json = $plan;

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

            $session->metadata_json = $metadata;
            $session->status = InterrogationSession::STATUS_COMPLETED;
            $session->phase = InterrogationSession::PHASE_PLANNING;
            $session->finished_at = CarbonImmutable::now('UTC');
            $session->save();

            $writer = new InterrogationEventWriter($session);
            $writer->appendPlan($plan);
            $writer->appendSystem([
                'notice' => 'plan_exported',
                'summary_path' => $summaryPath,
                'plan_path' => $planPath,
            ]);
        } catch (\Throwable $throwable) {
            report($throwable);

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
}
