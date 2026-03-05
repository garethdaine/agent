<?php

namespace App\Services\Runtime\Adapters;

use App\DTOs\Runtime\RuntimeContext;
use App\DTOs\Runtime\ToolResult;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use Illuminate\Support\Facades\Gate;

/**
 * Exposes Agent Ops job and run operations as runtime tools (list, show, run_now, stop).
 */
class AgentApiToolAdapter extends AbstractToolAdapter
{
    public function name(): string
    {
        return 'agent_api';
    }

    public function schema(): array
    {
        return [
            'operations' => ['list_jobs', 'show_job', 'run_now', 'list_runs', 'show_run', 'stop_run'],
            'parameters' => [
                'operation' => ['type' => 'string', 'required' => true],
                'agent_job_id' => ['type' => 'integer', 'required_for' => ['show_job', 'run_now', 'list_runs']],
                'agent_run_id' => ['type' => 'integer', 'required_for' => ['show_run', 'stop_run']],
            ],
        ];
    }

    public function authorize(RuntimeContext $context, array $args): bool
    {
        return parent::authorize($context, $args);
    }

    protected function getRequiredCapability(array $args): string
    {
        $operation = $args['operation'] ?? '';
        $mutations = ['run_now', 'stop_run'];

        return in_array($operation, $mutations, true) ? 'runtime_command' : 'query';
    }

    public function execute(RuntimeContext $context, array $args): ToolResult
    {
        $startTime = hrtime(true);
        $operation = $args['operation'] ?? '';

        return match ($operation) {
            'list_jobs' => $this->listJobs($context, $startTime),
            'show_job' => $this->showJob($context, $args, $startTime),
            'run_now' => $this->runNow($context, $args, $startTime),
            'list_runs' => $this->listRuns($context, $args, $startTime),
            'show_run' => $this->showRun($context, $args, $startTime),
            'stop_run' => $this->stopRun($context, $args, $startTime),
            default => ToolResult::failure("Unknown operation: {$operation}", $this->duration($startTime)),
        };
    }

    private function listJobs(RuntimeContext $context, int $startTime): ToolResult
    {
        $jobs = AgentJob::query()
            ->where('user_id', $context->user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'workflow_key', 'is_enabled']);

        return ToolResult::success([
            'jobs' => $jobs->toArray(),
            'count' => $jobs->count(),
        ], $this->duration($startTime));
    }

    private function showJob(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $jobId = (int) ($args['agent_job_id'] ?? 0);
        $job = AgentJob::query()->where('user_id', $context->user->id)->find($jobId);

        if ($job === null) {
            return ToolResult::failure("Job {$jobId} not found", $this->duration($startTime));
        }
        if (Gate::denies('view', $job)) {
            return ToolResult::failure('Not authorized to view this job', $this->duration($startTime));
        }

        return ToolResult::success($job->only(['id', 'name', 'workflow_key', 'is_enabled', 'cron_expression']), $this->duration($startTime));
    }

    private function runNow(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $jobId = (int) ($args['agent_job_id'] ?? 0);
        $job = AgentJob::query()->where('user_id', $context->user->id)->find($jobId);

        if ($job === null) {
            return ToolResult::failure("Job {$jobId} not found", $this->duration($startTime));
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
            'message' => "Run {$run->id} queued",
        ], $this->duration($startTime));
    }

    private function listRuns(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $jobId = (int) ($args['agent_job_id'] ?? 0);
        $job = AgentJob::query()->where('user_id', $context->user->id)->find($jobId);

        if ($job === null) {
            return ToolResult::failure("Job {$jobId} not found", $this->duration($startTime));
        }
        if (Gate::denies('view', $job)) {
            return ToolResult::failure('Not authorized to view this job', $this->duration($startTime));
        }

        $runs = $job->runs()->latest()->limit(20)->get(['id', 'status', 'trigger_type', 'started_at', 'finished_at']);

        return ToolResult::success([
            'runs' => $runs->toArray(),
            'count' => $runs->count(),
        ], $this->duration($startTime));
    }

    private function showRun(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $runId = (int) ($args['agent_run_id'] ?? 0);
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
            'status' => $run->status,
            'trigger_type' => $run->trigger_type,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ], $this->duration($startTime));
    }

    private function stopRun(RuntimeContext $context, array $args, int $startTime): ToolResult
    {
        $runId = (int) ($args['agent_run_id'] ?? 0);
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
                'message' => 'Run is not active',
            ], $this->duration($startTime));
        }

        $run->update([
            'status' => AgentJobRun::STATUS_KILLED,
            'finished_at' => now(),
        ]);

        return ToolResult::success([
            'run_id' => $run->id,
            'status' => $run->status,
            'message' => 'Run stopped',
        ], $this->duration($startTime));
    }

    private function duration(int $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
