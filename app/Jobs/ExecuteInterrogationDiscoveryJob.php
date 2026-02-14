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

class ExecuteInterrogationDiscoveryJob implements ShouldQueue
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

        $fromPhase = (int) $session->phase;

        $moved = $transitions->transitionPhase(
            (int) $session->id,
            $fromPhase,
            InterrogationSession::PHASE_DISCOVERY,
            InterrogationSession::STATUS_DISCOVERING,
            [InterrogationSession::STATUS_SETUP, InterrogationSession::STATUS_PAUSED],
            ['started_at' => CarbonImmutable::now('UTC')]
        );

        if (! $moved) {
            return;
        }

        $session->refresh();
        $writer = new InterrogationEventWriter($session);
        $writer->appendPhaseTransition($fromPhase, (int) $session->phase, (string) $session->status, [
            'at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);

        try {
            $adapter = $adapterFactory->make((string) $session->runner_type);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'discovery');
            $discoveryPrompt = 'Inspect the project and emit concise discovery progress updates while preparing requirements interrogation.';
            $command = $adapter->buildDiscoveryCommand($session, $discoveryPrompt, $systemPrompt);

            $process = new Process($command, (string) $session->project_directory, $adapter->buildEnvironment($session));
            $process->setTimeout(600);
            $process->start();

            $stdoutBuffer = '';
            $stderrBuffer = '';

            while ($process->isRunning()) {
                $stdoutBuffer = $this->consumeStreamChunk($adapterFactory, $session, $writer, $stdoutBuffer, $process->getIncrementalOutput());
                $stderrBuffer = $this->consumeStreamChunk($adapterFactory, $session, $writer, $stderrBuffer, $process->getIncrementalErrorOutput());

                usleep(250_000);
            }

            $stdoutBuffer = $this->consumeStreamChunk($adapterFactory, $session, $writer, $stdoutBuffer, $process->getIncrementalOutput(), true);
            $stderrBuffer = $this->consumeStreamChunk($adapterFactory, $session, $writer, $stderrBuffer, $process->getIncrementalErrorOutput(), true);

            if ($process->getExitCode() !== 0) {
                $transitions->transition(
                    (int) $session->id,
                    InterrogationSession::ACTIVE_STATUSES,
                    InterrogationSession::STATUS_FAILED,
                    [
                        'error_code' => 'DISCOVERY_COMMAND_FAILED',
                        'error_summary' => trim($process->getErrorOutput()) ?: 'Discovery command failed.',
                        'finished_at' => CarbonImmutable::now('UTC'),
                    ],
                );

                $session->refresh();
                $writer->appendError([
                    'code' => 'DISCOVERY_COMMAND_FAILED',
                    'message' => trim($process->getErrorOutput()) ?: 'Discovery command failed.',
                ]);

                return;
            }

            $movedToInterrogating = $transitions->transitionPhase(
                (int) $session->id,
                InterrogationSession::PHASE_DISCOVERY,
                InterrogationSession::PHASE_INTERROGATION,
                InterrogationSession::STATUS_INTERROGATING,
                [InterrogationSession::STATUS_DISCOVERING],
            );

            if (! $movedToInterrogating) {
                return;
            }

            $session->refresh();
            $writer = new InterrogationEventWriter($session);
            $writer->appendPhaseTransition(
                InterrogationSession::PHASE_DISCOVERY,
                InterrogationSession::PHASE_INTERROGATION,
                (string) $session->status,
                ['at' => CarbonImmutable::now('UTC')->toIso8601String()]
            );

            ExecuteInterrogationRoundJob::dispatch((int) $session->id, 'Start requirements interrogation. Ask the first question.');
        } catch (\Throwable $throwable) {
            report($throwable);

            $transitions->transition(
                (int) $session->id,
                InterrogationSession::ACTIVE_STATUSES,
                InterrogationSession::STATUS_FAILED,
                [
                    'error_code' => 'DISCOVERY_RUNTIME_EXCEPTION',
                    'error_summary' => $throwable->getMessage(),
                    'finished_at' => CarbonImmutable::now('UTC'),
                ],
            );

            $session->refresh();
            $writer = new InterrogationEventWriter($session);
            $writer->appendError([
                'code' => 'DISCOVERY_RUNTIME_EXCEPTION',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    private function consumeStreamChunk(
        AdapterFactory $adapterFactory,
        InterrogationSession $session,
        InterrogationEventWriter $writer,
        string $buffer,
        string $chunk,
        bool $flush = false,
    ): string {
        if ($chunk !== '') {
            $buffer .= $chunk;
        }

        $parts = preg_split('/\R/', $buffer) ?: [];

        if (! $flush) {
            $buffer = (string) array_pop($parts);
        } else {
            $buffer = '';
        }

        $adapter = $adapterFactory->make((string) $session->runner_type);

        foreach ($parts as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parsed = $adapter->parseStreamEvent($line);

            if ($parsed === null) {
                $writer->appendDiscoveryActivity([
                    'source' => (string) $session->runner_type,
                    'message' => $line,
                ]);

                continue;
            }

            $payload = is_array($parsed['payload'] ?? null) ? $parsed['payload'] : [];
            $payload['stream_type'] = (string) ($parsed['type'] ?? 'message');
            $writer->appendDiscoveryActivity($payload);

            if (isset($payload['cli_session_id']) && is_string($payload['cli_session_id']) && $payload['cli_session_id'] !== '' && $session->cli_session_id !== $payload['cli_session_id']) {
                $session->cli_session_id = $payload['cli_session_id'];
                $session->save();
            }
        }

        return $buffer;
    }
}
