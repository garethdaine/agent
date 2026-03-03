<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RepoAnalysis\PruneRepoAnalysisArtifactsJob;
use Illuminate\Console\Command;

class RepoAnalysisPruneArtifactsCommand extends Command
{
    protected $signature = 'code-analysis:prune-artifacts {--dry-run : Show how many artifacts would be pruned}';

    protected $description = 'Delete code-analysis task artifacts older than configured retention window';

    public function handle(): int
    {
        /** @var array{dry_run: bool, retention_days: int, cutoff: string, candidates: int, deleted: int, failed: int} $summary */
        $summary = (new PruneRepoAnalysisArtifactsJob((bool) $this->option('dry-run')))->handle();

        $this->info(sprintf(
            'Code Analysis artifact prune: deleted %d of %d candidates older than %d days (failed: %d).',
            $summary['deleted'],
            $summary['candidates'],
            $summary['retention_days'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}
