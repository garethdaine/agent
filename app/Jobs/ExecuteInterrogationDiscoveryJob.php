<?php

namespace App\Jobs;

use App\Jobs\Compliance\LessonExtractionJob;
use App\Models\InterrogationSession;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Interrogation\AdapterFactory;
use App\Support\Interrogation\InterrogationEventWriter;
use App\Support\Interrogation\SessionStateTransitionService;
use App\Support\Interrogation\SystemPromptResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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
            $adapter = $adapterFactory->make((string) $session->runner_type, $session->model);
            $systemPrompt = $promptResolver->resolveForPhase($session, 'discovery');
            $discoveryPrompt = 'Inspect this project for requirements interrogation prep. Focus on app/, routes/, resources/js/, config/, database/migrations/, docs/, and tests/. '
                .'Do not scan vendor/, node_modules/, storage/, or bootstrap/cache/. Emit concise human-readable progress updates only.';
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

                $this->dispatchLessonIfEnabled($session, 'DISCOVERY_COMMAND_FAILED', trim($process->getErrorOutput()) ?: 'Discovery command failed.');

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

            // Start interrogation with a fresh CLI session to avoid carrying discovery stream state.
            $session->cli_session_id = null;
            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $metadata['dynamic_question_bank_mode'] = (bool) config('agent.interrogation.dynamic_question_bank_enabled', true);
            $metadata['asked_canonical_keys'] = array_values(array_unique(array_filter(
                (array) ($metadata['asked_canonical_keys'] ?? []),
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            )));
            $metadata['answered_canonical_keys'] = array_values(array_unique(array_filter(
                (array) ($metadata['answered_canonical_keys'] ?? []),
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            )));
            $metadata['suppressed_canonical_keys'] = array_values(array_unique(array_filter(
                (array) ($metadata['suppressed_canonical_keys'] ?? []),
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            )));
            $metadata['locked_policy_snapshot'] = is_array($metadata['locked_policy_snapshot'] ?? null)
                ? $metadata['locked_policy_snapshot']
                : [];
            $session->metadata_json = $metadata;
            $session->save();

            $typeLabel = trim((string) $session->interrogation_type);
            $brief = trim((string) ($session->feature_brief ?? ''));
            $kickoffMessage = 'Start requirements interrogation.'
                ."\nSession type: ".($typeLabel !== '' ? $typeLabel : 'general').'.'
                ."\nAsk the first question.";

            if ($brief !== '') {
                $kickoffMessage .= "\n\nSession brief:\n".$brief;
            }

            ExecuteInterrogationRoundJob::dispatch(
                (int) $session->id,
                $kickoffMessage,
                true,
            );
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

            $this->dispatchLessonIfEnabled($session, 'DISCOVERY_RUNTIME_EXCEPTION', $throwable->getMessage());
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

        $adapter = $adapterFactory->make((string) $session->runner_type, $session->model);

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

            if (isset($payload['cli_session_id']) && is_string($payload['cli_session_id']) && $payload['cli_session_id'] !== '' && $session->cli_session_id !== $payload['cli_session_id']) {
                $session->cli_session_id = $payload['cli_session_id'];
                $session->save();
            }

            $message = trim((string) ($payload['message'] ?? ''));
            if ($message === '') {
                continue;
            }

            $writer->appendDiscoveryActivity($payload);
        }

        return $buffer;
    }

    private function dispatchLessonIfEnabled(InterrogationSession $session, string $errorCode, string $errorMessage): void
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::COMPLIANCE_LESSONS)) {
            return;
        }

        try {
            LessonExtractionJob::dispatch(
                lessonContent: sprintf('Interrogation discovery failed (%s): %s', $errorCode, substr($errorMessage, 0, 300)),
                source: 'interrogation_discovery_failure',
                projectDirectory: base_path(),
                context: [
                    'task_title' => 'Interrogation Discovery',
                    'task_category' => 'interrogation',
                    'runner_type' => (string) $session->runner_type,
                    'session_id' => $session->id,
                    'error_code' => $errorCode,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('ExecuteInterrogationDiscoveryJob: Failed to dispatch lesson', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
