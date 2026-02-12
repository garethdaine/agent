<?php

namespace App\Support\Agent;

use App\Jobs\ExecuteAgentRunJob;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentSystemState;
use App\Models\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class DispatchDueService
{
    public function __construct(
        private ReconcileActiveRunsService $reconcileActiveRuns,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function dispatch(?CarbonImmutable $now = null): array
    {
        $reconcileError = null;

        try {
            $this->reconcileActiveRuns->reconcile('tick');
        } catch (\Throwable $throwable) {
            report($throwable);
            $reconcileError = $throwable->getMessage();
        }

        $tickStartedAt = CarbonImmutable::now('UTC');
        $nowMinute = ($now ?? $tickStartedAt)->startOfMinute();

        $watermark = $this->getWatermark();
        $scanFrom = $this->determineScanStart($watermark, $nowMinute);
        $scanTo = $nowMinute;

        $windows = $this->collectDueWindows($scanFrom, $scanTo)
            ->sortBy([
                ['due_window', 'asc'],
                ['agent_job_id', 'asc'],
            ])
            ->values();

        $dispatchCandidates = $windows->take(20);
        $deferredCapacityCount = max(0, $windows->count() - $dispatchCandidates->count());

        $dispatchedCount = 0;
        $skippedOverlapCount = 0;
        $skippedCooldownCount = 0;
        $errorCount = $reconcileError === null ? 0 : 1;

        foreach ($dispatchCandidates as $candidate) {
            try {
                $result = $this->createScheduledRun($candidate['job'], $candidate['due_window'], $tickStartedAt);

                if ($result === 'queued') {
                    $dispatchedCount++;
                } elseif ($result === 'skipped_overlap') {
                    $skippedOverlapCount++;
                } elseif ($result === 'skipped_cooldown') {
                    $skippedCooldownCount++;
                }
            } catch (\Throwable $throwable) {
                report($throwable);
                $errorCount++;
            }
        }

        if ($deferredCapacityCount > 0) {
            $this->recordDeferredCapacityAudit($deferredCapacityCount, $scanFrom, $scanTo);
        }

        $tickFinishedAt = CarbonImmutable::now('UTC');

        $this->setWatermark($scanTo);

        SchedulerHeartbeat::query()->updateOrCreate(
            ['source' => 'scheduler_dispatch'],
            [
                'last_seen_at' => $tickFinishedAt,
                'meta_json' => [
                    'dispatched_count' => $dispatchedCount,
                    'skipped_overlap_count' => $skippedOverlapCount,
                    'skipped_cooldown_count' => $skippedCooldownCount,
                    'error_count' => $errorCount,
                    'deferred_capacity_count' => $deferredCapacityCount,
                    'tick_started_at' => $tickStartedAt->toIso8601String(),
                    'tick_finished_at' => $tickFinishedAt->toIso8601String(),
                    'watermark_minute_utc' => $scanTo->toIso8601String(),
                    'reconcile_error' => $reconcileError,
                ],
            ]
        );

        return [
            'dispatched_count' => $dispatchedCount,
            'skipped_overlap_count' => $skippedOverlapCount,
            'skipped_cooldown_count' => $skippedCooldownCount,
            'error_count' => $errorCount,
            'deferred_capacity_count' => $deferredCapacityCount,
            'watermark_minute_utc' => $scanTo->toIso8601String(),
        ];
    }

    private function determineScanStart(?CarbonImmutable $watermark, CarbonImmutable $nowMinute): CarbonImmutable
    {
        if ($watermark === null) {
            return $nowMinute;
        }

        $candidate = $watermark->addMinute();

        $lookbackFloor = $nowMinute->subMinutes(15);

        if ($candidate->lessThan($lookbackFloor)) {
            return $lookbackFloor;
        }

        if ($candidate->greaterThan($nowMinute)) {
            return $nowMinute;
        }

        return $candidate;
    }

    private function getWatermark(): ?CarbonImmutable
    {
        $state = AgentSystemState::query()->find('dispatch_last_minute_utc');

        if ($state === null || ! is_string($state->value) || $state->value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($state->value, 'UTC')->startOfMinute();
        } catch (\Throwable) {
            return null;
        }
    }

    private function setWatermark(CarbonImmutable $watermark): void
    {
        AgentSystemState::query()->updateOrCreate(
            ['key' => 'dispatch_last_minute_utc'],
            [
                'value' => $watermark->toIso8601String(),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]
        );
    }

    /**
     * @return Collection<int, array{job:AgentJob,agent_job_id:int,due_window:CarbonImmutable}>
     */
    private function collectDueWindows(CarbonImmutable $scanFrom, CarbonImmutable $scanTo): Collection
    {
        $jobs = AgentJob::query()
            ->whereNull('deleted_at')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get();

        $windows = collect();

        foreach ($jobs as $job) {
            $cron = CronExpression::factory($job->cron_expression);
            $addedForJob = 0;

            for ($cursor = $scanFrom; $cursor->lessThanOrEqualTo($scanTo); $cursor = $cursor->addMinute()) {
                if ($addedForJob >= 5) {
                    break;
                }

                $zoned = $cursor->setTimezone($job->timezone);

                if (! $cron->isDue($zoned)) {
                    continue;
                }
                if (! $this->matchesCronMinuteAndHour($job->cron_expression, $zoned)) {
                    continue;
                }

                $windows->push([
                    'job' => $job,
                    'agent_job_id' => (int) $job->id,
                    'due_window' => $cursor,
                ]);

                $addedForJob++;
            }
        }

        return $windows;
    }

    private function matchesCronMinuteAndHour(string $cronExpression, CarbonImmutable $zoned): bool
    {
        $parts = preg_split('/\s+/', trim($cronExpression)) ?: [];
        if (count($parts) < 2) {
            return false;
        }

        $minuteField = (string) $parts[0];
        $hourField = (string) $parts[1];

        return $this->matchesNumericField($minuteField, $zoned->minute, 0, 59)
            && $this->matchesNumericField($hourField, $zoned->hour, 0, 23);
    }

    private function matchesNumericField(string $field, int $value, int $min, int $max): bool
    {
        foreach (explode(',', $field) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $step = null;
            if (str_contains($segment, '/')) {
                [$segmentBase, $stepToken] = explode('/', $segment, 2);
                $segment = trim($segmentBase);
                $step = (int) trim($stepToken);

                if ($step <= 0) {
                    continue;
                }
            }

            $rangeStart = $min;
            $rangeEnd = $max;

            if ($segment !== '*') {
                if (str_contains($segment, '-')) {
                    [$startToken, $endToken] = explode('-', $segment, 2);
                    $rangeStart = (int) trim($startToken);
                    $rangeEnd = (int) trim($endToken);
                } else {
                    $rangeStart = (int) $segment;
                    $rangeEnd = $rangeStart;
                }
            }

            if ($rangeStart < $min || $rangeEnd > $max || $rangeStart > $rangeEnd) {
                continue;
            }

            if ($value < $rangeStart || $value > $rangeEnd) {
                continue;
            }

            if ($step === null) {
                return true;
            }

            if ((($value - $rangeStart) % $step) === 0) {
                return true;
            }
        }

        return false;
    }

    private function createScheduledRun(AgentJob $job, CarbonImmutable $dueWindow, CarbonImmutable $tickTimestamp): string
    {
        $status = AgentJobRun::STATUS_QUEUED;
        $metadata = [
            'output_truncated' => false,
            'redaction_count' => 0,
            'approval_required' => false,
        ];
        $finishedAt = null;

        $hasActiveOverlap = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->whereIn('status', AgentJobRun::ACTIVE_STATUSES)
            ->exists();

        if ($hasActiveOverlap) {
            $status = AgentJobRun::STATUS_SKIPPED;
            $metadata['skip_reason'] = 'overlap';
            $finishedAt = $tickTimestamp;
        }

        if ($status !== AgentJobRun::STATUS_SKIPPED && $this->isInCooldown($job, $dueWindow)) {
            $status = AgentJobRun::STATUS_SKIPPED;
            $metadata['skip_reason'] = 'cooldown';
            $finishedAt = $tickTimestamp;
        }

        try {
            $run = AgentJobRun::query()->create([
                'agent_job_id' => $job->id,
                'user_id' => $job->user_id,
                'initiated_by_user_id' => null,
                'trigger_type' => AgentJobRun::TRIGGER_SCHEDULE,
                'due_window_utc_minute' => $dueWindow,
                'status' => $status,
                'duration_ms' => 0,
                'stdout_bytes_pre' => 0,
                'stdout_bytes_post' => 0,
                'stderr_bytes_pre' => 0,
                'stderr_bytes_post' => 0,
                'metadata_json' => $metadata,
                'finished_at' => $finishedAt,
            ]);
        } catch (QueryException $queryException) {
            if ($this->isUniqueViolation($queryException)) {
                return 'duplicate';
            }

            throw $queryException;
        }

        if ($status === AgentJobRun::STATUS_QUEUED) {
            ExecuteAgentRunJob::dispatch($run->id)
                ->onConnection('redis')
                ->onQueue('agent');

            return 'queued';
        }

        $skipReason = (string) ($metadata['skip_reason'] ?? 'unknown');
        $writer = new RunEventWriter($run);
        $writer->appendLifecycle([
            'type' => 'skip_reason',
            'reason' => $skipReason,
            'at' => $tickTimestamp->toIso8601String(),
        ]);

        return $skipReason === 'overlap' ? 'skipped_overlap' : 'skipped_cooldown';
    }

    private function isInCooldown(AgentJob $job, CarbonImmutable $dueWindow): bool
    {
        if ((int) $job->cooldown_seconds <= 0) {
            return false;
        }

        $lastRun = AgentJobRun::query()
            ->where('agent_job_id', $job->id)
            ->where('status', '!=', AgentJobRun::STATUS_SKIPPED)
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->first();

        if ($lastRun === null || $lastRun->finished_at === null) {
            return false;
        }

        $cooldownEndsAt = CarbonImmutable::parse($lastRun->finished_at, 'UTC')->addSeconds((int) $job->cooldown_seconds);

        return $dueWindow->lessThan($cooldownEndsAt);
    }

    private function isUniqueViolation(QueryException $queryException): bool
    {
        $sqlState = $queryException->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }

    private function recordDeferredCapacityAudit(
        int $deferredCapacityCount,
        CarbonImmutable $scanFrom,
        CarbonImmutable $scanTo
    ): void {
        $this->auditLogger->recordSystemAction(
            action: 'deferred_capacity',
            targetType: 'scheduler_dispatch',
            targetId: 'scheduler_dispatch',
            changedFields: [
                'deferred_capacity_count',
                'scan_from_utc',
                'scan_to_utc',
            ],
            after: [
                'deferred_capacity_count' => $deferredCapacityCount,
                'scan_from_utc' => $scanFrom->toIso8601String(),
                'scan_to_utc' => $scanTo->toIso8601String(),
            ],
        );
    }
}
