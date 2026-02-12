<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentJobRun;
use App\Models\SchedulerHeartbeat;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\RunEventWriter;
use App\Support\Agent\RunStateTransitionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRunController extends Controller
{
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

        return response()->json([
            'data' => [
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
            ],
        ]);
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
            $durationMs = $run->started_at
                ? max(0, CarbonImmutable::parse($run->started_at, 'UTC')->diffInMilliseconds($finishedAt))
                : 0;

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

    public function schedulerHealth(): JsonResponse
    {
        $heartbeat = SchedulerHeartbeat::query()->where('source', 'scheduler_dispatch')->first();

        if ($heartbeat === null) {
            return response()->json([
                'data' => [
                    'status' => 'unknown',
                    'last_seen_at' => null,
                    'age_seconds' => null,
                ],
            ]);
        }

        $lastSeen = CarbonImmutable::parse($heartbeat->last_seen_at, 'UTC');
        $ageSeconds = $lastSeen->diffInSeconds(CarbonImmutable::now('UTC'));

        $status = 'healthy';
        if ($ageSeconds > 300) {
            $status = 'down';
        } elseif ($ageSeconds > 90) {
            $status = 'degraded';
        }

        return response()->json([
            'data' => [
                'status' => $status,
                'last_seen_at' => $this->toRfc3339Millis($lastSeen),
                'age_seconds' => $ageSeconds,
                'meta' => $heartbeat->meta_json,
            ],
        ]);
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
