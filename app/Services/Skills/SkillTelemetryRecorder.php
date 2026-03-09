<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\AgentSkill;
use App\Services\Telemetry\IngestionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SkillTelemetryRecorder
{
    public function __construct(
        private readonly IngestionService $ingestionService,
    ) {}

    public function recordInvocation(
        AgentSkill $skill,
        string $runAttemptId,
        string $workflowKey,
        string $outcome,
        int $durationMs,
        int $tokenUsage,
        ?string $failureReason = null,
    ): void {
        // Attempt ledger write
        try {
            $this->ingestionService->ingest([
                'event_id' => Str::uuid()->toString(),
                'run_attempt_id' => $runAttemptId,
                'event_type' => 'skill.'.$outcome,
                'event_sequence' => 0,
                'terminal' => false,
                'workflow_key' => $workflowKey,
                'payload_json' => json_encode([
                    'skill_id' => (string) $skill->id,
                    'skill_name' => $skill->name,
                    'skill_version' => $skill->version,
                    'team_id' => $skill->team_id,
                    'duration_ms' => $durationMs,
                    'token_usage' => $tokenUsage,
                    'outcome' => $outcome,
                    'failure_reason' => $failureReason,
                ], JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Skill telemetry ledger write failed', [
                'skill_id' => $skill->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Always attempt skill row update
        try {
            // SAFETY: no user input in raw query — atomic counter increment only
            DB::table('agent_skills')
                ->where('id', $skill->id)
                ->update([
                    'invocation_count' => DB::raw('invocation_count + 1'),
                    'last_invoked_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Skill row update failed', [
                'skill_id' => $skill->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
