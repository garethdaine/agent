<?php

declare(strict_types=1);

namespace App\Services\Escalation;

use App\Models\AgentJob;
use App\Support\Time\AgentClock;
use Illuminate\Support\Facades\DB;

final class WorkflowGovernanceService
{
    public function __construct(
        private readonly AgentClock $clock,
    ) {}

    public function pauseWorkflow(AgentJob $job, string $actorId, string $reason, string $runId, bool $force = false): AgentJob
    {
        return DB::transaction(function () use ($job, $actorId, $reason, $runId, $force): AgentJob {
            /** @var AgentJob $locked */
            $locked = AgentJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            $previousState = $this->stateFor($locked);
            $now = $this->clock->nowUtc();

            $locked->forceFill([
                'governance_paused_at' => $now,
                'governance_pause_reason' => $reason,
                'governance_paused_by' => $actorId,
            ])->save();

            $this->recordAudit(
                workflowKey: (string) $locked->workflow_key,
                actorId: $actorId,
                runId: $runId,
                previousState: $previousState,
                newState: 'paused',
                reason: $reason,
                action: $force ? 'force_pause' : 'pause',
                authorized: true,
                metadata: ['job_id' => (int) $locked->id]
            );

            return $locked->refresh();
        });
    }

    public function resumeWorkflow(AgentJob $job, string $actorId, string $reason, string $runId, bool $force = false): AgentJob
    {
        return DB::transaction(function () use ($job, $actorId, $reason, $runId, $force): AgentJob {
            /** @var AgentJob $locked */
            $locked = AgentJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

            $previousState = $this->stateFor($locked);

            $locked->forceFill([
                'governance_paused_at' => null,
                'governance_pause_reason' => null,
                'governance_paused_by' => null,
            ])->save();

            $this->recordAudit(
                workflowKey: (string) $locked->workflow_key,
                actorId: $actorId,
                runId: $runId,
                previousState: $previousState,
                newState: 'active',
                reason: $reason,
                action: $force ? 'force_resume' : 'resume',
                authorized: true,
                metadata: ['job_id' => (int) $locked->id]
            );

            return $locked->refresh();
        });
    }

    public function recordDeniedAttempt(
        AgentJob $job,
        string $actorId,
        string $newState,
        string $reason,
        string $runId,
        string $action
    ): void {
        $this->recordAudit(
            workflowKey: (string) $job->workflow_key,
            actorId: $actorId,
            runId: $runId,
            previousState: $this->stateFor($job),
            newState: $newState,
            reason: $reason,
            action: $action,
            authorized: false,
            metadata: ['job_id' => (int) $job->id]
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function recordAudit(
        string $workflowKey,
        string $actorId,
        string $runId,
        string $previousState,
        string $newState,
        string $reason,
        string $action,
        bool $authorized,
        ?array $metadata = null,
    ): void {
        $now = $this->clock->nowUtc();

        DB::table('manual_override_audits')->insert([
            'workflow_key' => $workflowKey,
            'actor_id' => $actorId,
            'timestamp' => $now,
            'run_id' => $runId,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'reason' => $reason,
            'action' => $action,
            'authorized' => $authorized,
            'metadata_json' => $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function stateFor(AgentJob $job): string
    {
        return $job->governance_paused_at !== null ? 'paused' : 'active';
    }
}
