<?php

namespace App\Console\Commands;

use App\Support\Agent\MaintenancePruneService;
use Illuminate\Console\Command;

class AgentPruneCommand extends Command
{
    protected $signature = 'agent:prune
        {--runs : Prune run rows}
        {--events : Prune run event rows}
        {--jobs : Hard-prune soft-deleted jobs}
        {--audit : Prune audit rows}
        {--dry-run : Preview candidate rows without deleting}
        {--chunk=500 : Chunk size per deletion batch}
        {--json : Output machine-readable JSON summary}';

    protected $description = 'Prune agent maintenance domains with resumable checkpoints';

    public function handle(MaintenancePruneService $pruneService): int
    {
        $domains = [];
        foreach (['runs', 'events', 'jobs', 'audit'] as $domain) {
            if ((bool) $this->option($domain)) {
                $domains[] = $domain;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $summary = $pruneService->prune($domains, $dryRun, $chunkSize);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'agent:prune completed dry_run=%s started=%s finished=%s',
            $summary['dry_run'] ? 'true' : 'false',
            $summary['started_at'],
            $summary['finished_at'],
        ));

        foreach ($summary['domains'] as $domain => $domainSummary) {
            $this->line(sprintf(
                '[%s] processed=%d deleted=%d would_delete=%s chunks=%d checkpoint=%s',
                $domain,
                (int) $domainSummary['processed_rows'],
                (int) $domainSummary['deleted_rows'],
                $domainSummary['would_delete_rows'] === null
                    ? 'n/a'
                    : (string) $domainSummary['would_delete_rows'],
                (int) $domainSummary['chunk_count'],
                $domainSummary['checkpoint_id'] === null ? 'n/a' : (string) $domainSummary['checkpoint_id'],
            ));
        }

        return self::SUCCESS;
    }
}
