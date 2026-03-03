<?php

declare(strict_types=1);

namespace App\Jobs\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PruneRepoAnalysisArtifactsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public bool $dryRun = false,
    ) {
        $this->onConnection(config('repo_analysis.queue.connection', 'redis'));
        $this->onQueue(config('repo_analysis.queue.name', 'code-analysis'));
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   retention_days: int,
     *   cutoff: string,
     *   candidates: int,
     *   deleted: int,
     *   failed: int
     * }
     */
    public function handle(): array
    {
        $retentionDays = (int) config('repo_analysis.retention.task_artifacts_ttl_days', 30);
        $cutoff = CarbonImmutable::now('UTC')->subDays($retentionDays);

        $query = RepoAnalysisArtifact::query()
            ->whereNotNull('repo_analysis_task_id')
            ->where('created_at', '<', $cutoff);

        $candidates = (clone $query)->count();
        $deleted = 0;
        $failed = 0;

        (clone $query)
            ->orderBy('id')
            ->chunkById(200, function ($artifacts) use (&$deleted, &$failed): void {
                foreach ($artifacts as $artifact) {
                    try {
                        if ($this->dryRun) {
                            $deleted++;

                            continue;
                        }

                        $artifact->delete();
                        $deleted++;
                    } catch (Throwable) {
                        $failed++;
                    }
                }
            });

        return [
            'dry_run' => $this->dryRun,
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toIso8601String(),
            'candidates' => $candidates,
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }
}

