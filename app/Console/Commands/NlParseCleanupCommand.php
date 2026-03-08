<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NlParseAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NlParseCleanupCommand extends Command
{
    protected $signature = 'nl-parse:cleanup';

    protected $description = 'Delete nl_parse_attempts records older than retention period';

    public function handle(): int
    {
        $retentionDays = config('agent.nl_parse.retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        $totalDeleted = 0;
        $batchSize = 1000;

        do {
            $deleted = NlParseAttempt::query()
                ->where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();

            $totalDeleted += $deleted;
        } while ($deleted === $batchSize);

        $message = "NL parse cleanup: deleted {$totalDeleted} records older than {$retentionDays} days";

        Log::info($message);
        $this->info($message);

        return Command::SUCCESS;
    }
}
