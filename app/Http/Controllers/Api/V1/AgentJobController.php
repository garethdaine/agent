<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentJobRequest;
use App\Http\Requests\Agent\UpdateAgentJobRequest;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\TaskMarkdownStorage;
use App\Support\Agent\UsageLimitState;
use App\Support\Agent\WorkflowKey;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AgentJobController extends Controller
{
    public function __construct(private UsageLimitState $usageLimitState) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->agentJobs()->newQuery();

        $deleted = $request->string('deleted')->toString();
        if ($deleted === '1' || $deleted === 'true') {
            $query->onlyTrashed();
        } elseif ($deleted === 'all') {
            $query->withTrashed();
        }

        $q = trim($request->string('q')->toString());
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('is_enabled')) {
            $query->where('is_enabled', filter_var($request->input('is_enabled'), FILTER_VALIDATE_BOOL));
        }

        if ($request->filled('runner_type')) {
            $query->where('runner_type', $request->string('runner_type')->toString());
        }

        $workflowKey = trim($request->string('workflow_key')->toString());
        if ($workflowKey !== '') {
            $query->where('workflow_key', $workflowKey);
        }

        $source = strtolower(trim($request->string('source')->toString()));
        if ($source === 'build') {
            $query->where(function ($builder): void {
                $builder->where('name', 'like', 'Interrogation Build S%')
                    ->orWhere('env_json->AGENT_JOB_SOURCE', 'interrogation_build');
            });
        } elseif ($source === 'user') {
            $query->where(function ($builder): void {
                $builder->where('name', 'not like', 'Interrogation Build S%')
                    ->where(function ($inner): void {
                        $inner->whereNull('env_json->AGENT_JOB_SOURCE')
                            ->orWhere('env_json->AGENT_JOB_SOURCE', '!=', 'interrogation_build');
                    });
            });
        } else {
            $source = '';
        }

        $sort = $request->string('sort', 'name')->toString();
        $dir = strtolower($request->string('dir', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'updated_at', 'created_at', 'is_enabled'], true)) {
            $sort = 'name';
        }

        if ($sort === 'is_enabled') {
            $query->orderBy('is_enabled', 'desc')->orderBy('name', 'asc');
        } else {
            $query->orderBy($sort, $dir);
        }

        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));
        $jobs = $query->paginate($perPage)->withQueryString();

        $data = collect($jobs->items())->map(fn (AgentJob $job): array => $this->transformJob($job, false))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
                'last_page' => $jobs->lastPage(),
            ],
            'links' => [
                'first' => $jobs->url(1),
                'last' => $jobs->url($jobs->lastPage()),
                'prev' => $jobs->previousPageUrl(),
                'next' => $jobs->nextPageUrl(),
            ],
            'filters' => [
                'q' => $request->input('q'),
                'is_enabled' => $request->input('is_enabled'),
                'runner_type' => $request->input('runner_type'),
                'active' => $request->input('active'),
                'deleted' => $request->input('deleted'),
                'source' => $source,
            ],
            'sort' => [
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);
        $includeTaskContent = $request->boolean('include_task_content', false);

        return response()->json([
            'data' => $this->transformJob($job, $includeTaskContent),
        ]);
    }

    public function showByWorkflowKey(Request $request, string $workflowKey): JsonResponse
    {
        $job = $request->user()->agentJobs()
            ->where('workflow_key', $workflowKey)
            ->firstOrFail();

        return response()->json([
            'data' => $this->transformJob($job),
        ]);
    }

    public function store(
        StoreAgentJobRequest $request,
        AuditLogger $auditLogger,
        TaskMarkdownStorage $taskMarkdownStorage
    ): JsonResponse {
        $validated = $request->validated();
        $workflowKey = WorkflowKey::resolve(
            $validated['workflow_key'] ?? null,
            (string) $validated['name']
        );

        $existing = $request->user()->agentJobs()
            ->whereNull('deleted_at')
            ->where('name', $validated['name'])
            ->exists();

        if ($existing) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'name' => ['The name has already been taken.'],
            ]);
        }

        $workflowKeyExists = $request->user()->agentJobs()
            ->whereNull('deleted_at')
            ->where('workflow_key', $workflowKey)
            ->exists();

        if ($workflowKeyExists) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'workflow_key' => ['The workflow key has already been taken.'],
            ]);
        }

        $taskMarkdownPath = $this->resolveTaskMarkdownPath($request, $validated, $taskMarkdownStorage);

        $job = $request->user()->agentJobs()->create([
            'name' => $validated['name'],
            'workflow_key' => $workflowKey,
            'description' => $validated['description'] ?? null,
            'cron_expression' => $validated['cron_expression'],
            'timezone' => $validated['timezone'],
            'is_enabled' => $validated['is_enabled'] ?? true,
            'max_runtime_seconds' => $validated['max_runtime_seconds'],
            'cooldown_seconds' => $validated['cooldown_seconds'],
            'runner_type' => $validated['runner_type'],
            'command_template' => $request->normalizedCommandTemplate(),
            'task_markdown_path' => $taskMarkdownPath,
            'working_directory' => $validated['working_directory'],
            'env_json' => $validated['env_json'] ?? null,
            'active_hours_config' => $validated['active_hours_config'] ?? null,
            'last_validated_executable_path' => $request->resolvedExecutablePath(),
            'star_preamble_enabled' => $validated['star_preamble_enabled'] ?? null,
            'targeted_retry_enabled' => $validated['targeted_retry_enabled'] ?? null,
            'max_retries' => $validated['max_retries'] ?? null,
        ]);

        $auditLogger->recordUserAction(
            request: $request,
            action: 'job.create',
            targetType: 'agent_job',
            targetId: (int) $job->id,
            ownerUserId: (int) $job->user_id,
            changedFields: array_keys($job->getAttributes()),
            before: null,
            after: $job->only([
                'id',
                'name',
                'workflow_key',
                'cron_expression',
                'timezone',
                'is_enabled',
                'runner_type',
                'max_runtime_seconds',
                'cooldown_seconds',
                'task_markdown_path',
                'working_directory',
            ]),
        );

        return response()->json([
            'data' => $this->transformJob($job),
        ], 201);
    }

    public function update(
        UpdateAgentJobRequest $request,
        int $id,
        AuditLogger $auditLogger,
        TaskMarkdownStorage $taskMarkdownStorage
    ): JsonResponse {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);
        $before = $job->only([
            'name',
            'workflow_key',
            'description',
            'cron_expression',
            'timezone',
            'is_enabled',
            'max_runtime_seconds',
            'cooldown_seconds',
            'runner_type',
            'command_template',
            'task_markdown_path',
            'working_directory',
            'env_json',
            'active_hours_config',
            'last_validated_executable_path',
        ]);
        $validated = $request->validated();

        $existing = $request->user()->agentJobs()
            ->whereNull('deleted_at')
            ->where('name', $validated['name'])
            ->where('id', '!=', $job->id)
            ->exists();

        if ($existing) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'name' => ['The name has already been taken.'],
            ]);
        }

        $workflowKey = WorkflowKey::resolve(
            $validated['workflow_key'] ?? $job->workflow_key,
            (string) $validated['name'],
            (int) $job->id
        );

        $workflowKeyExists = $request->user()->agentJobs()
            ->whereNull('deleted_at')
            ->where('workflow_key', $workflowKey)
            ->where('id', '!=', $job->id)
            ->exists();

        if ($workflowKeyExists) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'workflow_key' => ['The workflow key has already been taken.'],
            ]);
        }

        $taskMarkdownPath = $this->resolveTaskMarkdownPath($request, $validated, $taskMarkdownStorage);

        $data = [
            'name' => $validated['name'],
            'workflow_key' => $workflowKey,
            'description' => $validated['description'] ?? null,
            'cron_expression' => $validated['cron_expression'],
            'timezone' => $validated['timezone'],
            'is_enabled' => $validated['is_enabled'] ?? $job->is_enabled,
            'max_runtime_seconds' => $validated['max_runtime_seconds'],
            'cooldown_seconds' => $validated['cooldown_seconds'],
            'runner_type' => $validated['runner_type'],
            'command_template' => $request->normalizedCommandTemplate(),
            'task_markdown_path' => $taskMarkdownPath,
            'working_directory' => $validated['working_directory'],
            'env_json' => $validated['env_json'] ?? null,
            'last_validated_executable_path' => $request->resolvedExecutablePath(),
        ];

        if ($request->boolean('disable_active_hours')) {
            $data['active_hours_config'] = null;
        } elseif ($request->has('active_hours_config')) {
            $data['active_hours_config'] = $validated['active_hours_config'];
        }

        // Handle STAR configuration fields
        if ($request->has('star_preamble_enabled')) {
            $data['star_preamble_enabled'] = $validated['star_preamble_enabled'];
        }
        if ($request->has('targeted_retry_enabled')) {
            $data['targeted_retry_enabled'] = $validated['targeted_retry_enabled'];
        }
        if ($request->has('max_retries')) {
            $data['max_retries'] = $validated['max_retries'];
        }

        $job->fill($data);
        $job->save();

        $after = $job->only(array_keys($before));
        $changedFields = [];
        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changedFields[] = $field;
            }
        }

        if ($changedFields !== []) {
            $auditLogger->recordUserAction(
                request: $request,
                action: 'job.update',
                targetType: 'agent_job',
                targetId: (int) $job->id,
                ownerUserId: (int) $job->user_id,
                changedFields: $changedFields,
                before: $before,
                after: $after,
            );
        }

        return response()->json([
            'data' => $this->transformJob($job),
        ]);
    }

    public function toggle(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);
        $before = ['is_enabled' => (bool) $job->is_enabled];
        $job->is_enabled = ! $job->is_enabled;
        $job->save();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'job.toggle',
            targetType: 'agent_job',
            targetId: (int) $job->id,
            ownerUserId: (int) $job->user_id,
            changedFields: ['is_enabled'],
            before: $before,
            after: ['is_enabled' => (bool) $job->is_enabled],
        );

        return response()->json([
            'data' => $this->transformJob($job),
        ]);
    }

    public function destroy(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $job = $request->user()->agentJobs()->findOrFail($id);
        $before = ['deleted_at' => optional($job->deleted_at)?->toIso8601String()];
        $job->delete();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'job.delete',
            targetType: 'agent_job',
            targetId: (int) $job->id,
            ownerUserId: (int) $job->user_id,
            changedFields: ['deleted_at'],
            before: $before,
            after: ['deleted_at' => optional($job->deleted_at)?->toIso8601String()],
        );

        return response()->json([
            'data' => [
                'deleted' => true,
                'id' => $job->id,
            ],
        ]);
    }

    public function restore(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);
        $before = ['deleted_at' => optional($job->deleted_at)?->toIso8601String()];

        if ($job->trashed()) {
            $job->restore();
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'job.restore',
            targetType: 'agent_job',
            targetId: (int) $job->id,
            ownerUserId: (int) $job->user_id,
            changedFields: ['deleted_at'],
            before: $before,
            after: ['deleted_at' => optional($job->deleted_at)?->toIso8601String()],
        );

        return response()->json([
            'data' => $this->transformJob($job->fresh()),
        ]);
    }

    public function runs(Request $request, int $id): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);

        $runs = $job->runs()
            ->orderByDesc('created_at')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return response()->json([
            'data' => $runs->items(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
                'last_page' => $runs->lastPage(),
            ],
        ]);
    }

    public function runNow(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);
        $ignoreRateLimitHold = $request->boolean('ignore_rate_limit_hold', false);

        $fingerprint = hash('sha256', sprintf('run-now|%d|%d', $request->user()->id, $job->id));
        $cacheKey = 'agent:run-now:'.$fingerprint;

        try {
            $redis = Cache::store('redis');
            $existingRunId = $redis->get($cacheKey);
        } catch (\Throwable $throwable) {
            report($throwable);

            return ErrorEnvelope::make(
                'QUEUE_UNAVAILABLE',
                'Queue backend is unavailable.',
                503,
            );
        }

        if ($existingRunId !== null) {
            return response()->json([
                'data' => [
                    'accepted' => true,
                    'idempotent_replay' => true,
                    'run_id' => (int) $existingRunId,
                ],
            ], 202);
        }

        if ($job->governance_paused_at !== null) {
            return ErrorEnvelope::make(
                'WORKFLOW_GOVERNANCE_PAUSED',
                'This workflow is paused by governance policy and cannot start new runs.',
                409,
                [
                    'job_id' => $job->id,
                    'workflow_key' => $job->workflow_key,
                    'governance_pause_reason' => $job->governance_pause_reason,
                    'governance_paused_at' => $job->governance_paused_at?->toIso8601String(),
                ]
            );
        }

        $activeHold = $this->usageLimitState->getActiveHold((int) $job->id);
        if (! $ignoreRateLimitHold && $activeHold !== null) {
            return ErrorEnvelope::make(
                'JOB_RATE_LIMITED',
                'This job is temporarily paused due to an upstream usage/rate limit.',
                409,
                [
                    'job_id' => $job->id,
                    'hold_until' => $activeHold['hold_until']->toIso8601String(),
                ]
            );
        }

        $hasOverlap = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->whereIn('status', AgentJobRun::ACTIVE_STATUSES)
            ->exists();

        if ($hasOverlap) {
            return ErrorEnvelope::make(
                'RUN_OVERLAP_ACTIVE',
                'An active run already exists for this job.',
                409,
                ['job_id' => $job->id]
            );
        }

        try {
            $run = AgentJobRun::query()->create([
                'agent_job_id' => $job->id,
                'user_id' => $job->user_id,
                'initiated_by_user_id' => $request->user()->id,
                'trigger_type' => AgentJobRun::TRIGGER_MANUAL,
                'due_window_utc_minute' => null,
                'status' => AgentJobRun::STATUS_QUEUED,
                'duration_ms' => 0,
                'stdout_bytes_pre' => 0,
                'stdout_bytes_post' => 0,
                'stderr_bytes_pre' => 0,
                'stderr_bytes_post' => 0,
                'metadata_json' => [
                    'output_truncated' => false,
                    'redaction_count' => 0,
                    'approval_required' => false,
                ],
            ]);

            ExecuteAgentRunJob::dispatch($run->id)
                ->onConnection('redis')
                ->onQueue('agent');

            $redis->put($cacheKey, (string) $run->id, now()->addSeconds(3));

            $auditLogger->recordUserAction(
                request: $request,
                action: 'run.dispatch_manual',
                targetType: 'agent_job_run',
                targetId: (int) $run->id,
                ownerUserId: (int) $run->user_id,
                changedFields: ['status', 'trigger_type', 'agent_job_id'],
                before: null,
                after: [
                    'status' => $run->status,
                    'trigger_type' => $run->trigger_type,
                    'agent_job_id' => $run->agent_job_id,
                ],
            );
        } catch (QueryException|\Throwable $throwable) {
            report($throwable);

            return ErrorEnvelope::make(
                'QUEUE_UNAVAILABLE',
                'Queue backend is unavailable.',
                503,
            );
        }

        return response()->json([
            'data' => [
                'accepted' => true,
                'idempotent_replay' => false,
                'run_id' => $run->id,
                'accepted_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ],
        ], 202);
    }

    private function transformJob(AgentJob $job, bool $includeTaskContent = false): array
    {
        $lastRun = $job->runs()->latest('created_at')->first();

        $active = $job->runs()->whereIn('status', AgentJobRun::ACTIVE_STATUSES)->exists();

        $nextRunUtc = null;

        try {
            $cron = CronExpression::factory($job->cron_expression);
            $nextRun = CarbonImmutable::instance(
                $cron->getNextRunDate(now($job->timezone), 0, false, $job->timezone)
            )->setTimezone('UTC');

            $nextRunUtc = $nextRun->toIso8601String();
        } catch (\Throwable) {
            $nextRunUtc = null;
        }

        $activeHold = $this->usageLimitState->getActiveHold((int) $job->id);
        $jobScope = $this->isBuildJob($job) ? 'build' : 'user';

        $payload = [
            'id' => $job->id,
            'name' => $job->name,
            'workflow_key' => $job->workflow_key,
            'description' => $job->description,
            'cron_expression' => $job->cron_expression,
            'timezone' => $job->timezone,
            'is_enabled' => $job->is_enabled,
            'runner_type' => $job->runner_type,
            'command_template' => $job->command_template,
            'max_runtime_seconds' => $job->max_runtime_seconds,
            'cooldown_seconds' => $job->cooldown_seconds,
            'task_markdown_path' => $job->task_markdown_path,
            'working_directory' => $job->working_directory,
            'env_json' => $job->env_json,
            'active_hours_config' => $job->active_hours_config,
            'star_preamble_enabled' => $job->star_preamble_enabled,
            'targeted_retry_enabled' => $job->targeted_retry_enabled,
            'max_retries' => $job->max_retries,
            'deleted_at' => optional($job->deleted_at)?->toIso8601String(),
            'created_at' => optional($job->created_at)?->toIso8601String(),
            'updated_at' => optional($job->updated_at)?->toIso8601String(),
            'next_run_utc' => $nextRunUtc,
            'active_run' => $active,
            'last_run_status' => $lastRun?->status,
            'last_run_finished_at' => optional($lastRun?->finished_at)?->toIso8601String(),
            'rate_limit_hold_until' => $activeHold !== null ? $activeHold['hold_until']->toIso8601String() : null,
            'rate_limit_hold_active' => $activeHold !== null,
            'job_scope' => $jobScope,
            'is_build_job' => $jobScope === 'build',
        ];

        if ($includeTaskContent) {
            $payload['task_markdown_content'] = $this->readTaskMarkdownContent($job->task_markdown_path);
        }

        return $payload;
    }

    private function isBuildJob(AgentJob $job): bool
    {
        $name = trim((string) $job->name);
        if (str_starts_with($name, 'Interrogation Build S')) {
            return true;
        }

        $env = is_array($job->env_json) ? $job->env_json : [];

        return ($env['AGENT_JOB_SOURCE'] ?? null) === 'interrogation_build';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveTaskMarkdownPath(
        Request $request,
        array $validated,
        TaskMarkdownStorage $taskMarkdownStorage
    ): string {
        $content = trim((string) ($validated['task_markdown_content'] ?? ''));

        if ($content !== '') {
            return $taskMarkdownStorage->persistInlineContent($content, (int) $request->user()->id);
        }

        return (string) ($validated['task_markdown_path'] ?? '');
    }

    private function readTaskMarkdownContent(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $content = @file_get_contents($path);

        if (! is_string($content)) {
            return null;
        }

        return mb_substr($content, 0, 200_000);
    }
}
