<?php

declare(strict_types=1);

namespace App\Listeners\Org;

use App\Events\DelegationTaskVerified;
use App\Models\AgentRunEvent;
use App\Models\OrgRitualRun;
use App\Support\Org\OrgRitualRunService;

/**
 * Captures full attempt metadata as phase output when a ritual-linked
 * delegation task completes verification (pass or fail).
 *
 * Writes structured output to OrgRitualRun::phase_outputs via the
 * OrgRitualRunService::completePhase() method.
 */
class RitualPhaseOutputCaptureListener
{
    private const MAX_OUTPUT_BYTES = 65536; // 64 KB

    public function __construct(
        private readonly OrgRitualRunService $runService,
    ) {}

    public function handle(DelegationTaskVerified $event): void
    {
        $task = $event->task;
        $attempt = $event->attempt;

        $contract = $task->contract_json ?? [];
        $ritualRunId = $contract['ritual_run_id'] ?? null;
        $phaseId = $contract['phase_id'] ?? null;

        if ($ritualRunId === null || $phaseId === null) {
            return;
        }

        $run = OrgRitualRun::whereIn('state', OrgRitualRun::ACTIVE_STATES)
            ->find($ritualRunId);

        if ($run === null) {
            return;
        }

        // Don't overwrite existing phase output (e.g. council deliberation)
        $existingOutputs = $run->phase_outputs ?? [];
        if (isset($existingOutputs[$phaseId])) {
            return;
        }

        $agentJobRun = $attempt->agentJobRun;
        $agentOutput = $this->collectAgentOutput($attempt->agent_job_run_id);

        $payload = [
            'status' => $event->passed ? 'succeeded' : 'failed',
            'task_name' => $task->name,
            'duration_ms' => $attempt->duration_ms,
            'attempt_number' => $attempt->attempt_number,
            'agent_job_run_id' => $attempt->agent_job_run_id,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'completed_at' => $attempt->finished_at?->toIso8601String(),
            'agent_output' => $agentOutput,
            'exit_code' => $agentJobRun?->exit_code,
            'attempt_metadata' => $attempt->metadata_json ?? [],
            'captured_at' => now()->toIso8601String(),
        ];

        if (! $event->passed && $event->failedStepOrder !== null) {
            $payload['verification_failed_at_step'] = $event->failedStepOrder;
        }

        $this->runService->completePhase($run, $phaseId, $payload);
    }

    /**
     * Collect stdout events from agent_run_events for the given run,
     * concatenated and truncated to MAX_OUTPUT_BYTES from the tail.
     */
    private function collectAgentOutput(?int $agentJobRunId): string
    {
        if ($agentJobRunId === null) {
            return '';
        }

        $events = AgentRunEvent::where('agent_job_run_id', $agentJobRunId)
            ->where('event_type', 'stdout')
            ->orderBy('sequence')
            ->pluck('payload')
            ->implode('');

        if (strlen($events) > self::MAX_OUTPUT_BYTES) {
            return substr($events, -self::MAX_OUTPUT_BYTES);
        }

        return $events;
    }
}
