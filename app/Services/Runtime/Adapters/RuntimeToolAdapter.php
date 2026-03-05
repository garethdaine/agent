<?php

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Enums\Runtime\RuntimeMode;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use Illuminate\Support\Facades\Gate;

class RuntimeToolAdapter extends AbstractToolAdapter
{
    private const MUTATION_OPERATIONS = ['run', 'stop'];

    private const READ_OPERATIONS = ['status'];

    public function name(): string
    {
        return 'runtime';
    }

    public function schema(): array
    {
        return [
            'operations' => ['run', 'stop', 'status'],
            'parameters' => [
                'operation' => ['type' => 'string', 'required' => true],
                'agent_job_id' => ['type' => 'integer', 'required_for' => ['run', 'status']],
                'agent_run_id' => ['type' => 'integer', 'required_for' => ['stop', 'status']],
                'workflow_key' => ['type' => 'string', 'optional' => true],
            ],
        ];
    }

    public function authorize(RuntimeContext $context, array $args): bool
    {
        $operation = $args['operation'] ?? 'status';

        if ($context->mode === RuntimeMode::Safe && in_array($operation, self::MUTATION_OPERATIONS, true)) {
            return false;
        }

        return parent::authorize($context, $args);
    }

    protected function getRequiredCapability(array $args): string
    {
        $operation = $args['operation'] ?? 'status';

        if (in_array($operation, self::MUTATION_OPERATIONS, true)) {
            return 'runtime_command';
        }

        return 'query';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);
        $operation = $args['operation'] ?? 'status';

        return match ($operation) {
            'run' => $this->run($context, $args, $startTime),
            'stop' => $this->stop($context, $args, $startTime),
            'status' => $this->status($context, $args, $startTime),
            default => ToolResult::failure("Unknown operation: {$operation}", $this->duration($startTime)),
        };
    }

    private function run(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $jobId = isset($args['agent_job_id']) ? (int) $args['agent_job_id'] : null;
        $workflowKey = $args['workflow_key'] ?? null;

        $job = $this->resolveJob($context, $jobId, $workflowKey);
        if ($job === null) {
            return ToolResult::failure('Job not found or not authorized', $this->duration($startTime));
        }

        if (Gate::denies('update', $job)) {
            return ToolResult::failure('Not authorized to run this job', $this->duration($startTime));
        }

        $run = AgentJobRun::create([
            'agent_job_id' => $job->id,
            'user_id' => $context->user->id,
            'initiated_by_user_id' => $context->user->id,
            'status' => AgentJobRun::STATUS_QUEUED,
            'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
        ]);

        dispatch(new ExecuteAgentRunJob($run->id));

        return ToolResult::success([
            'run_id' => $run->id,
            'agent_job_id' => $job->id,
            'job_name' => $job->name,
            'status' => $run->status,
            'message' => "Run {$run->id} queued for execution",
        ], $this->duration($startTime));
    }

    private function stop(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $runId = isset($args['agent_run_id']) ? (int) $args['agent_run_id'] : null;
        if ($runId === null) {
            return ToolResult::failure('agent_run_id is required for stop', $this->duration($startTime));
        }

        $run = AgentJobRun::query()->with('job')->find($runId);
        if ($run === null) {
            return ToolResult::failure("Run {$runId} not found", $this->duration($startTime));
        }

        if ($run->job && Gate::denies('update', $run->job)) {
            return ToolResult::failure('Not authorized to stop this run', $this->duration($startTime));
        }

        if (! in_array($run->status, AgentJobRun::ACTIVE_STATUSES, true)) {
            return ToolResult::success([
                'run_id' => $run->id,
                'status' => $run->status,
                'message' => "Run {$run->id} is not active (current: {$run->status})",
            ], $this->duration($startTime));
        }

        $run->update([
            'status' => AgentJobRun::STATUS_KILLED,
            'finished_at' => now(),
        ]);

        return ToolResult::success([
            'run_id' => $run->id,
            'status' => $run->status,
            'message' => "Run {$run->id} stopped",
        ], $this->duration($startTime));
    }

    private function status(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $runId = isset($args['agent_run_id']) ? (int) $args['agent_run_id'] : null;
        $jobId = isset($args['agent_job_id']) ? (int) $args['agent_job_id'] : null;
        $workflowKey = $args['workflow_key'] ?? null;

        if ($runId !== null) {
            $run = AgentJobRun::query()->with('job')->find($runId);
            if ($run === null) {
                return ToolResult::failure("Run {$runId} not found", $this->duration($startTime));
            }
            if ($run->job && Gate::denies('view', $run->job)) {
                return ToolResult::failure('Not authorized to view this run', $this->duration($startTime));
            }

            return ToolResult::success([
                'run_id' => $run->id,
                'agent_job_id' => $run->agent_job_id,
                'job_name' => $run->job?->name,
                'status' => $run->status,
                'trigger_type' => $run->trigger_type,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'duration_ms' => $run->duration_ms,
            ], $this->duration($startTime));
        }

        $job = $this->resolveJob($context, $jobId, $workflowKey);
        if ($job === null) {
            return ToolResult::failure('Job not found or not authorized', $this->duration($startTime));
        }
        if (Gate::denies('view', $job)) {
            return ToolResult::failure('Not authorized to view this job', $this->duration($startTime));
        }

        $latestRun = $job->runs()->latest()->first();

        return ToolResult::success([
            'agent_job_id' => $job->id,
            'job_name' => $job->name,
            'workflow_key' => $job->workflow_key,
            'is_enabled' => $job->is_enabled,
            'latest_run_id' => $latestRun?->id,
            'latest_run_status' => $latestRun?->status,
            'latest_run_started_at' => $latestRun?->started_at?->toIso8601String(),
        ], $this->duration($startTime));
    }

    private function resolveJob(RuntimeContext $context, ?int $jobId, ?string $workflowKey): ?AgentJob
    {
        $query = AgentJob::query()->where('user_id', $context->user->id);

        if ($jobId !== null) {
            return $query->clone()->whereKey($jobId)->first();
        }
        if ($workflowKey !== null && $workflowKey !== '') {
            return $query->clone()->where('workflow_key', $workflowKey)->first();
        }

        return null;
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
