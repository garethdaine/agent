<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ComplianceSummaryResource;
use App\Models\AgentJobRun;
use App\Models\SchedulerHeartbeat;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\Duration;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\RunEventWriter;
use App\Support\Agent\RunStateTransitionService;
use App\Support\Agent\TargetedRetryService;
use App\Support\Compliance\LessonsManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRunController extends Controller
{
    public function dashboardMetrics(Request $request): JsonResponse
    {
        $window = strtolower($request->string('window', '24h')->toString());
        $windowHours = [
            '24h' => 24,
            '7d' => 24 * 7,
        ];

        if (! array_key_exists($window, $windowHours)) {
            return ErrorEnvelope::make('VALIDATION_ERROR', 'The given data was invalid.', 422, [
                'window' => ['The selected window is invalid.'],
            ]);
        }

        $hours = $windowHours[$window];
        $now = CarbonImmutable::now('UTC');
        $windowStart = $now->subHours($hours);
        $todayStart = $now->startOfDay();

        $baseQuery = AgentJobRun::query()->where('user_id', $request->user()->id);
        $windowTerminalQuery = AgentJobRun::query()
            ->where('user_id', $request->user()->id)
            ->where('created_at', '>=', $windowStart)
            ->whereIn('status', [
                AgentJobRun::STATUS_SUCCEEDED,
                AgentJobRun::STATUS_FAILED,
                AgentJobRun::STATUS_KILLED,
                AgentJobRun::STATUS_TIMED_OUT,
            ]);

        $runsToday = (clone $baseQuery)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $backlogCount = (clone $baseQuery)
            ->where('status', AgentJobRun::STATUS_QUEUED)
            ->count();

        $oldestQueuedAt = (clone $baseQuery)
            ->where('status', AgentJobRun::STATUS_QUEUED)
            ->min('created_at');

        $oldestQueuedAgeSeconds = 0;
        if ($oldestQueuedAt !== null) {
            $oldestQueuedAgeSeconds = CarbonImmutable::parse($oldestQueuedAt, 'UTC')
                ->diffInSeconds($now);
        }

        $windowTerminalTotal = (clone $windowTerminalQuery)->count();
        $windowSucceeded = (clone $windowTerminalQuery)
            ->where('status', AgentJobRun::STATUS_SUCCEEDED)
            ->count();
        $windowAverageDurationMs = (float) ((clone $windowTerminalQuery)->avg('duration_ms') ?? 0);

        $successRatePercent = $windowTerminalTotal > 0
            ? round(($windowSucceeded / $windowTerminalTotal) * 100, 1)
            : 0.0;

        return response()->json([
            'data' => [
                'window' => [
                    'key' => $window,
                    'hours' => $hours,
                    'from' => $this->toRfc3339Millis($windowStart),
                    'to' => $this->toRfc3339Millis($now),
                    'options' => [
                        ['key' => '24h', 'label' => 'Last 24h'],
                        ['key' => '7d', 'label' => 'Last 7 days'],
                    ],
                ],
                'metrics' => [
                    'runs_today' => $runsToday,
                    'success_rate_percent' => $successRatePercent,
                    'average_duration_ms' => (int) round($windowAverageDurationMs),
                    'backlog_count' => $backlogCount,
                    'oldest_queued_age_seconds' => $oldestQueuedAgeSeconds,
                    'window_terminal_total' => $windowTerminalTotal,
                    'window_succeeded_total' => $windowSucceeded,
                ],
                'scheduler' => $this->schedulerHealthSnapshot(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $hours = min(24 * 7, max(1, (int) $request->integer('hours', 24)));
        $limit = min(200, max(1, (int) $request->integer('limit', 50)));

        $runs = AgentJobRun::query()
            ->where('user_id', $request->user()->id)
            ->where('created_at', '>=', CarbonImmutable::now('UTC')->subHours($hours))
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $runs,
            'meta' => [
                'hours' => $hours,
                'limit' => $limit,
                'returned' => $runs->count(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $run = AgentJobRun::query()->with('job')->find($id);

        if ($run === null || $run->user_id !== $request->user()->id) {
            return ErrorEnvelope::make('NOT_FOUND', 'Resource not found.', 404);
        }

        $response = [
            'id' => $run->id,
            'agent_job_id' => $run->agent_job_id,
            'user_id' => $run->user_id,
            'initiated_by_user_id' => $run->initiated_by_user_id,
            'trigger_type' => $run->trigger_type,
            'due_window_utc_minute' => $this->toRfc3339Millis($run->due_window_utc_minute),
            'status' => $run->status,
            'pid' => $run->pid,
            'resolved_executable_path' => $run->resolved_executable_path,
            'started_at' => $this->toRfc3339Millis($run->started_at),
            'finished_at' => $this->toRfc3339Millis($run->finished_at),
            'exit_code' => $run->exit_code,
            'signal' => $run->signal,
            'duration_ms' => $run->duration_ms,
            'error_summary' => $run->error_summary,
            'error_code' => $run->error_code,
            'metadata_json' => $run->metadata_json,
            'output_stats' => [
                'stdout_bytes_pre' => $run->stdout_bytes_pre,
                'stdout_bytes_post' => $run->stdout_bytes_post,
                'stderr_bytes_pre' => $run->stderr_bytes_pre,
                'stderr_bytes_post' => $run->stderr_bytes_post,
                'redaction_count' => (int) (($run->metadata_json ?? [])['redaction_count'] ?? 0),
                'output_truncated' => (bool) (($run->metadata_json ?? [])['output_truncated'] ?? false),
            ],
            'links' => [
                'events' => '/agent/api/v1/runs/'.$run->id.'/events',
            ],
        ];

        $complianceData = $this->extractComplianceData($run->metadata_json ?? []);
        if (! empty($complianceData)) {
            $response['compliance_summary'] = (new ComplianceSummaryResource($complianceData))->toArray($request);
        }

        return response()->json([
            'data' => $response,
        ]);
    }

    /**
     * Extract compliance-related data from metadata.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function extractComplianceData(array $metadata): array
    {
        $complianceKeys = [
            'workflow_policy_version',
            'complexity_classification',
            'task_category',
            'plan_required',
            'plan_completed',
            'verification_required',
            'verification_completed',
            'compliance_block_reason',
            'compliance_remediation',
            'compliance_gates',
            'compliance_status',
        ];

        return array_intersect_key($metadata, array_flip($complianceKeys));
    }

    public function events(Request $request, int $id): JsonResponse
    {
        $run = AgentJobRun::query()->find($id);

        if ($run === null || $run->user_id !== $request->user()->id) {
            return ErrorEnvelope::make('NOT_FOUND', 'Resource not found.', 404);
        }

        $after = max(0, (int) $request->integer('after_sequence', 0));
        $limit = min(500, max(1, (int) $request->integer('limit', 100)));

        $events = $run->events()
            ->where('sequence', '>', $after)
            ->orderBy('sequence')
            ->limit($limit + 1)
            ->get();

        $hasMore = $events->count() > $limit;
        $returned = $events->take($limit)->values();
        $nextAfter = $returned->last()?->sequence ?? $after;

        return response()->json([
            'data' => $returned->map(fn ($event): array => [
                'id' => $event->id,
                'run_id' => $event->agent_job_run_id,
                'sequence' => $event->sequence,
                'event_type' => $event->event_type,
                'payload' => $event->payload,
                'reasoning_step' => $event->reasoning_step,
                'created_at' => $this->toRfc3339Millis($event->created_at),
                'event_ts' => $this->toRfc3339Millis($event->event_ts),
            ]),
            'meta' => [
                'after_sequence' => $after,
                'returned' => $returned->count(),
                'has_more' => $hasMore,
                'next_after_sequence' => $nextAfter,
            ],
        ]);
    }

    public function stop(
        Request $request,
        int $id,
        RunStateTransitionService $transitions,
        AuditLogger $auditLogger
    ): JsonResponse {
        $run = AgentJobRun::query()->find($id);

        if ($run === null || $run->user_id !== $request->user()->id) {
            return ErrorEnvelope::make('NOT_FOUND', 'Resource not found.', 404);
        }

        if (in_array($run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
            return response()->json([
                'data' => [
                    'run_id' => $run->id,
                    'status' => $run->status,
                    'accepted' => false,
                    'already_terminal' => true,
                ],
            ]);
        }

        if ($run->status === AgentJobRun::STATUS_STOPPING) {
            return response()->json([
                'data' => [
                    'run_id' => $run->id,
                    'status' => AgentJobRun::STATUS_STOPPING,
                    'accepted' => true,
                    'already_requested' => true,
                    'poll_after_ms' => 1000,
                    'requested_by_user_id' => $request->user()->id,
                    'accepted_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ],
            ], 202);
        }

        if (! in_array($run->status, [AgentJobRun::STATUS_QUEUED, AgentJobRun::STATUS_STARTING, AgentJobRun::STATUS_RUNNING], true)) {
            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Run is not in a stoppable state.', 409);
        }

        $previousStatus = (string) $run->status;

        if ($run->pid === null) {
            $metadata = (array) ($run->metadata_json ?? []);
            $metadata['termination_mode'] = 'pid_missing';
            $metadata['pid_not_found'] = true;

            $finishedAt = CarbonImmutable::now('UTC');
            $durationMs = Duration::millisecondsBetween($run->started_at, $finishedAt);
            if (($metadata['approval_required'] ?? false) === true) {
                $metadata['approval_required'] = false;
                $metadata['approval_resolved_at'] = $finishedAt->toIso8601String();
                $metadata['approval_resolution'] = AgentJobRun::STATUS_KILLED;
            }

            $transitioned = $transitions->transition(
                (int) $run->id,
                AgentJobRun::ACTIVE_STATUSES,
                AgentJobRun::STATUS_KILLED,
                [
                    'finished_at' => $finishedAt,
                    'duration_ms' => $durationMs,
                    'metadata_json' => $metadata,
                ]
            );

            if (! $transitioned) {
                $run->refresh();

                if (in_array($run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
                    return response()->json([
                        'data' => [
                            'run_id' => $run->id,
                            'status' => $run->status,
                            'accepted' => false,
                            'already_terminal' => true,
                        ],
                    ]);
                }

                return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Run state changed while processing stop.', 409);
            }

            $run->refresh();
            $writer = new RunEventWriter($run);
            $writer->appendLifecycle([
                'type' => 'state_transition',
                'to' => AgentJobRun::STATUS_KILLED,
                'at' => $finishedAt->toIso8601String(),
                'reason' => 'pid_missing',
            ]);

            $auditLogger->recordUserAction(
                request: $request,
                action: 'run.stop',
                targetType: 'agent_job_run',
                targetId: (int) $run->id,
                ownerUserId: (int) $run->user_id,
                changedFields: ['status', 'finished_at', 'metadata_json'],
                before: ['status' => $previousStatus],
                after: [
                    'status' => AgentJobRun::STATUS_KILLED,
                    'finished_at' => $this->toRfc3339Millis($run->finished_at),
                    'metadata_json' => $run->metadata_json,
                ],
            );

            return response()->json([
                'data' => [
                    'run_id' => $run->id,
                    'status' => AgentJobRun::STATUS_KILLED,
                    'accepted' => true,
                    'poll_after_ms' => 1000,
                    'requested_by_user_id' => $request->user()->id,
                    'accepted_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ],
            ], 202);
        }

        $transitioned = $transitions->transition(
            (int) $run->id,
            [AgentJobRun::STATUS_QUEUED, AgentJobRun::STATUS_STARTING, AgentJobRun::STATUS_RUNNING],
            AgentJobRun::STATUS_STOPPING
        );

        if (! $transitioned) {
            $run->refresh();

            if ($run->status === AgentJobRun::STATUS_STOPPING) {
                return response()->json([
                    'data' => [
                        'run_id' => $run->id,
                        'status' => AgentJobRun::STATUS_STOPPING,
                        'accepted' => true,
                        'already_requested' => true,
                        'poll_after_ms' => 1000,
                        'requested_by_user_id' => $request->user()->id,
                        'accepted_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    ],
                ], 202);
            }

            if (in_array($run->status, AgentJobRun::TERMINAL_STATUSES, true)) {
                return response()->json([
                    'data' => [
                        'run_id' => $run->id,
                        'status' => $run->status,
                        'accepted' => false,
                        'already_terminal' => true,
                    ],
                ]);
            }

            return ErrorEnvelope::make('RUN_TRANSITION_CONFLICT', 'Run state changed while processing stop.', 409);
        }

        $run->refresh();

        if (function_exists('posix_kill')) {
            @posix_kill((int) $run->pid, SIGTERM);
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'run.stop',
            targetType: 'agent_job_run',
            targetId: (int) $run->id,
            ownerUserId: (int) $run->user_id,
            changedFields: ['status'],
            before: ['status' => $previousStatus],
            after: ['status' => AgentJobRun::STATUS_STOPPING],
        );

        return response()->json([
            'data' => [
                'run_id' => $run->id,
                'status' => AgentJobRun::STATUS_STOPPING,
                'accepted' => true,
                'poll_after_ms' => 1000,
                'requested_by_user_id' => $request->user()->id,
                'accepted_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ],
        ], 202);
    }

    public function retry(Request $request, int $id, TargetedRetryService $retryService): JsonResponse
    {
        $run = AgentJobRun::with('job.user')->findOrFail($id);

        // Authorization check
        if ($run->job->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Validate run is in failed state
        if ($run->status !== AgentJobRun::STATUS_FAILED) {
            return response()->json([
                'error' => 'Only failed runs can be retried',
            ], 422);
        }

        $customPrompt = $request->input('retry_prompt');

        $retryRun = $retryService->dispatchRetry($run, $customPrompt);

        return response()->json([
            'data' => [
                'id' => $retryRun->id,
                'status' => $retryRun->status,
                'retry_of_run_id' => $run->id,
            ]
        ], 201);
    }

    public function confirmSuggestedLesson(Request $request, int $id, LessonsManager $lessons): JsonResponse
    {
        $run = AgentJobRun::with('job.user')->findOrFail($id);

        if ($run->job->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $metadata = $run->metadata_json ?? [];
        $suggestedLesson = $metadata['failure_mode_hint']['suggested_lesson'] ?? null;

        if (! $suggestedLesson) {
            return response()->json([
                'error' => 'No suggested lesson available for this run',
            ], 422);
        }

        // Append lesson
        $lessons->appendLesson(
            base_path(),
            $suggestedLesson,
            'failure_mode_classification',
            [
                'task_title' => $run->job->name,
                'task_category' => $run->job->task_category?->value ?? 'unknown',
                'runner_type' => $run->job->runner_type,
                'run_id' => $run->id,
            ]
        );

        // Update metadata
        $metadata['suggested_lesson_confirmed'] = true;
        $metadata['suggested_lesson_confirmed_at'] = now()->toIso8601String();
        $run->update(['metadata_json' => $metadata]);

        return response()->json(['success' => true]);
    }

    public function schedulerHealth(): JsonResponse
    {
        return response()->json([
            'data' => $this->schedulerHealthSnapshot(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulerHealthSnapshot(): array
    {
        $heartbeat = SchedulerHeartbeat::query()->where('source', 'scheduler_dispatch')->first();

        if ($heartbeat === null) {
            return [
                'status' => 'unknown',
                'last_seen_at' => null,
                'age_seconds' => null,
            ];
        }

        $lastSeen = CarbonImmutable::parse($heartbeat->last_seen_at, 'UTC');
        $ageSeconds = $lastSeen->diffInSeconds(CarbonImmutable::now('UTC'));

        $status = 'healthy';
        if ($ageSeconds > 300) {
            $status = 'down';
        } elseif ($ageSeconds > 90) {
            $status = 'degraded';
        }

        return [
            'status' => $status,
            'last_seen_at' => $this->toRfc3339Millis($lastSeen),
            'age_seconds' => $ageSeconds,
            'meta' => $heartbeat->meta_json,
        ];
    }

    private function toRfc3339Millis(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $dt = CarbonImmutable::parse($value, 'UTC');

        return $dt->utc()->format('Y-m-d\\TH:i:s.v\\Z');
    }
}
