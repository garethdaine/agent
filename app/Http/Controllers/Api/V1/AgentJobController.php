<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentJobRequest;
use App\Http\Requests\Agent\UpdateAgentJobRequest;
use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Support\Agent\ErrorEnvelope;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AgentJobController extends Controller
{
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

        $data = collect($jobs->items())->map(fn (AgentJob $job): array => $this->transformJob($job))->values();

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
            ],
            'sort' => [
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function store(StoreAgentJobRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $existing = $request->user()->agentJobs()
            ->whereNull('deleted_at')
            ->where('name', $validated['name'])
            ->exists();

        if ($existing) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'name' => ['The name has already been taken.'],
            ]);
        }

        $job = $request->user()->agentJobs()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'cron_expression' => $validated['cron_expression'],
            'timezone' => $validated['timezone'],
            'is_enabled' => $validated['is_enabled'] ?? true,
            'max_runtime_seconds' => $validated['max_runtime_seconds'],
            'cooldown_seconds' => $validated['cooldown_seconds'],
            'runner_type' => $validated['runner_type'],
            'command_template' => $request->normalizedCommandTemplate(),
            'task_markdown_path' => $validated['task_markdown_path'],
            'working_directory' => $validated['working_directory'],
            'env_json' => $validated['env_json'] ?? null,
            'last_validated_executable_path' => $request->resolvedExecutablePath(),
        ]);

        return response()->json([
            'data' => $this->transformJob($job),
        ], 201);
    }

    public function update(UpdateAgentJobRequest $request, int $id): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);
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

        $job->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'cron_expression' => $validated['cron_expression'],
            'timezone' => $validated['timezone'],
            'is_enabled' => $validated['is_enabled'] ?? $job->is_enabled,
            'max_runtime_seconds' => $validated['max_runtime_seconds'],
            'cooldown_seconds' => $validated['cooldown_seconds'],
            'runner_type' => $validated['runner_type'],
            'command_template' => $request->normalizedCommandTemplate(),
            'task_markdown_path' => $validated['task_markdown_path'],
            'working_directory' => $validated['working_directory'],
            'env_json' => $validated['env_json'] ?? null,
            'last_validated_executable_path' => $request->resolvedExecutablePath(),
        ]);
        $job->save();

        return response()->json([
            'data' => $this->transformJob($job),
        ]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);
        $job->is_enabled = ! $job->is_enabled;
        $job->save();

        return response()->json([
            'data' => $this->transformJob($job),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $job = $request->user()->agentJobs()->findOrFail($id);
        $job->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'id' => $job->id,
            ],
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);

        if ($job->trashed()) {
            $job->restore();
        }

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

    public function runNow(Request $request, int $id): JsonResponse
    {
        $job = $request->user()->agentJobs()->withTrashed()->findOrFail($id);

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
                ],
            ]);

            ExecuteAgentRunJob::dispatch($run->id)
                ->onConnection('redis')
                ->onQueue('agent');

            $redis->put($cacheKey, (string) $run->id, now()->addSeconds(3));
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

    private function transformJob(AgentJob $job): array
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

        return [
            'id' => $job->id,
            'name' => $job->name,
            'description' => $job->description,
            'cron_expression' => $job->cron_expression,
            'timezone' => $job->timezone,
            'is_enabled' => $job->is_enabled,
            'runner_type' => $job->runner_type,
            'max_runtime_seconds' => $job->max_runtime_seconds,
            'cooldown_seconds' => $job->cooldown_seconds,
            'task_markdown_path' => $job->task_markdown_path,
            'working_directory' => $job->working_directory,
            'deleted_at' => optional($job->deleted_at)?->toIso8601String(),
            'created_at' => optional($job->created_at)?->toIso8601String(),
            'updated_at' => optional($job->updated_at)?->toIso8601String(),
            'next_run_utc' => $nextRunUtc,
            'active_run' => $active,
            'last_run_status' => $lastRun?->status,
            'last_run_finished_at' => optional($lastRun?->finished_at)?->toIso8601String(),
        ];
    }
}
