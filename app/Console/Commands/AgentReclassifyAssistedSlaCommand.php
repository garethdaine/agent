<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reliability\AssistedSlaExpiryReclassifier;
use Illuminate\Console\Command;

class AgentReclassifyAssistedSlaCommand extends Command
{
    protected $signature = 'agent:reliability-assisted-sla {--chunk=200 : Chunk size for batch updates}';

    protected $description = 'Reclassify assisted runs that exceeded the verification SLA to failed.';

    public function handle(AssistedSlaExpiryReclassifier $reclassifier): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        $reclassified = $reclassifier->reclassifyExpired($chunkSize);

        $this->info(sprintf('Reclassified %d assisted run(s) that exceeded SLA.', $reclassified));

        return self::SUCCESS;
    }
}
