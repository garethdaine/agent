<?php

declare(strict_types=1);

namespace App\Support\Agent;

use App\Models\AgentAuditLog;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\AgentMaintenanceCheckpoint;
use App\Models\AgentRunEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaintenancePruneService
{
    public const DOMAINS = ['runs', 'events', 'jobs', 'audit'];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<int, string>  $domains
     * @return array{domains:array<string, array<string, mixed>>,dry_run:bool,started_at:string,finished_at:string}
     */
    public function prune(array $domains, bool $dryRun, int $chunkSize): array
    {
        $startedAt = CarbonImmutable::now('UTC');
        $normalizedDomains = $this->normalizeDomains($domains);

        $results = [];
        foreach ($normalizedDomains as $domain) {
            $results[$domain] = $this->pruneDomain($domain, $dryRun, $chunkSize);
        }

        return [
            'domains' => $results,
            'dry_run' => $dryRun,
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, string>  $domains
     * @return array<int, string>
     */
    private function normalizeDomains(array $domains): array
    {
        if ($domains === []) {
            return self::DOMAINS;
        }

        $normalized = [];

        foreach ($domains as $domain) {
            $domain = strtolower(trim($domain));

            if (in_array($domain, self::DOMAINS, true)) {
                $normalized[] = $domain;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>
     */
    private function pruneDomain(string $domain, bool $dryRun, int $chunkSize): array
    {
        $chunkSize = max(1, min(5000, $chunkSize));
        $startedAt = CarbonImmutable::now('UTC');
        $processed = 0;
        $deleted = 0;
        $lastProcessedId = 0;
        $chunks = 0;

        $checkpoint = null;

        if (! $dryRun) {
            $checkpoint = AgentMaintenanceCheckpoint::query()
                ->where('domain', $domain)
                ->whereIn('status', ['pending', 'running', 'failed'])
                ->latest('id')
                ->first();

            if ($checkpoint === null) {
                $checkpoint = AgentMaintenanceCheckpoint::query()->create([
                    'domain' => $domain,
                    'status' => 'running',
                    'last_processed_id' => null,
                    'processed_rows' => 0,
                    'progress_json' => [
                        'dry_run' => false,
                        'status' => 'running',
                    ],
                    'started_at' => $startedAt,
                ]);
            } else {
                $checkpoint->status = 'running';
                $checkpoint->started_at = $checkpoint->started_at ?? $startedAt;
                $checkpoint->progress_json = array_merge((array) ($checkpoint->progress_json ?? []), [
                    'dry_run' => false,
                    'status' => 'running',
                ]);
                $checkpoint->save();

                $lastProcessedId = (int) ($checkpoint->last_processed_id ?? 0);
                $processed = (int) ($checkpoint->processed_rows ?? 0);
            }
        }

        try {
            while (true) {
                $ids = $this->candidateIds($domain, $lastProcessedId, $chunkSize);

                if ($ids === []) {
                    break;
                }

                $chunks++;
                $chunkCount = count($ids);
                $processed += $chunkCount;
                $lastProcessedId = max($ids);

                if (! $dryRun) {
                    $deleted += $this->deleteIds($domain, $ids);

                    $checkpoint->last_processed_id = $lastProcessedId;
                    $checkpoint->processed_rows = $processed;
                    $checkpoint->progress_json = array_merge((array) ($checkpoint->progress_json ?? []), [
                        'chunk_count' => $chunks,
                        'last_chunk_size' => $chunkCount,
                        'last_processed_id' => $lastProcessedId,
                        'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    ]);
                    $checkpoint->save();
                }

                Log::info('agent.maintenance.prune.progress', [
                    'domain' => $domain,
                    'dry_run' => $dryRun,
                    'chunk' => $chunks,
                    'chunk_size' => $chunkCount,
                    'processed_rows' => $processed,
                    'deleted_rows' => $deleted,
                    'last_processed_id' => $lastProcessedId,
                ]);
            }

            $finishedAt = CarbonImmutable::now('UTC');

            if (! $dryRun) {
                $checkpoint->status = 'completed';
                $checkpoint->finished_at = $finishedAt;
                $checkpoint->progress_json = array_merge((array) ($checkpoint->progress_json ?? []), [
                    'status' => 'completed',
                    'completed_at' => $finishedAt->toIso8601String(),
                    'deleted_rows' => $deleted,
                    'processed_rows' => $processed,
                ]);
                $checkpoint->save();

                $this->auditLogger->recordSystemAction(
                    action: 'maintenance.prune.'.$domain,
                    targetType: 'maintenance',
                    targetId: $domain,
                    changedFields: ['domain', 'deleted_rows', 'processed_rows', 'chunk_count'],
                    after: [
                        'domain' => $domain,
                        'deleted_rows' => $deleted,
                        'processed_rows' => $processed,
                        'chunk_count' => $chunks,
                        'checkpoint_id' => $checkpoint->id,
                    ],
                );
            }

            return [
                'domain' => $domain,
                'dry_run' => $dryRun,
                'processed_rows' => $processed,
                'deleted_rows' => $deleted,
                'would_delete_rows' => $dryRun ? $processed : null,
                'chunk_count' => $chunks,
                'last_processed_id' => $lastProcessedId,
                'checkpoint_id' => $checkpoint?->id,
                'started_at' => $startedAt->toIso8601String(),
                'finished_at' => $finishedAt->toIso8601String(),
            ];
        } catch (\Throwable $throwable) {
            if (! $dryRun && $checkpoint !== null) { // @phpstan-ignore notIdentical.alwaysTrue
                $checkpoint->status = 'failed';
                $checkpoint->progress_json = array_merge((array) ($checkpoint->progress_json ?? []), [
                    'status' => 'failed',
                    'failed_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    'error' => $throwable->getMessage(),
                ]);
                $checkpoint->save();
            }

            throw $throwable;
        }
    }

    /**
     * @return array<int, int>
     */
    private function candidateIds(string $domain, int $lastProcessedId, int $limit): array
    {
        return match ($domain) {
            'runs' => $this->candidateRunIds($lastProcessedId, $limit),
            'events' => $this->candidateEventIds($lastProcessedId, $limit),
            'jobs' => $this->candidateJobIds($lastProcessedId, $limit),
            'audit' => $this->candidateAuditIds($lastProcessedId, $limit),
            default => [],
        };
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function deleteIds(string $domain, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return match ($domain) {
            'runs' => AgentJobRun::query()->whereIn('id', $ids)->delete(),
            'events' => AgentRunEvent::query()->whereIn('id', $ids)->delete(),
            'jobs' => $this->forceDeleteJobs($ids),
            'audit' => AgentAuditLog::query()->whereIn('id', $ids)->delete(),
            default => 0,
        };
    }

    /**
     * @return array<int, int>
     */
    private function candidateRunIds(int $lastProcessedId, int $limit): array
    {
        $threshold = CarbonImmutable::now('UTC')->subDays(30);

        $sub = DB::table('agent_job_runs as r')
            ->selectRaw('r.id, r.agent_job_id, r.created_at, r.status, ROW_NUMBER() OVER (PARTITION BY r.agent_job_id ORDER BY r.created_at DESC, r.id DESC) as rn')
            ->where('r.id', '>', $lastProcessedId)
            ->whereIn('r.status', AgentJobRun::TERMINAL_STATUSES);

        return DB::query()
            ->fromSub($sub, 'ranked')
            ->where('ranked.created_at', '<', $threshold)
            ->where('ranked.rn', '>', 20)
            ->orderBy('ranked.id')
            ->limit($limit)
            ->pluck('ranked.id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function candidateEventIds(int $lastProcessedId, int $limit): array
    {
        $threshold = CarbonImmutable::now('UTC')->subDays(7);

        return DB::table('agent_run_events as e')
            ->join('agent_job_runs as r', 'r.id', '=', 'e.agent_job_run_id')
            ->where('e.id', '>', $lastProcessedId)
            ->where('e.created_at', '<', $threshold)
            ->whereNotIn('r.status', AgentJobRun::ACTIVE_STATUSES)
            ->orderBy('e.id')
            ->limit($limit)
            ->pluck('e.id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function candidateJobIds(int $lastProcessedId, int $limit): array
    {
        $threshold = CarbonImmutable::now('UTC')->subDays(30);

        return AgentJob::query()
            ->onlyTrashed()
            ->where('id', '>', $lastProcessedId)
            ->where('deleted_at', '<', $threshold)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function candidateAuditIds(int $lastProcessedId, int $limit): array
    {
        $threshold = CarbonImmutable::now('UTC')->subDays(90);

        return AgentAuditLog::query()
            ->where('id', '>', $lastProcessedId)
            ->where('created_at', '<', $threshold)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function forceDeleteJobs(array $ids): int
    {
        $deleted = 0;

        AgentJob::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->each(function (AgentJob $job) use (&$deleted): void {
                $job->forceDelete();
                $deleted++;
            });

        return $deleted;
    }
}
