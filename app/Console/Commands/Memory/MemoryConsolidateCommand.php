<?php

declare(strict_types=1);

namespace App\Console\Commands\Memory;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\ConsolidationService;
use Illuminate\Console\Command;

/**
 * Memory Consolidation Command.
 *
 * Runs consolidation operations:
 * - Backfill retry: Retries failed memory formations
 * - Vector deduplication: Merges duplicate embeddings
 *
 * Usage:
 *   php artisan memory:consolidate
 *   php artisan memory:consolidate --user=1
 *   php artisan memory:consolidate --type=backfill
 */
class MemoryConsolidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'memory:consolidate
        {--user= : Scope consolidation to a specific user ID}
        {--type= : Run specific type only: backfill, dedup}';

    /**
     * The console command description.
     */
    protected $description = 'Run memory consolidation (backfill retries and vector deduplication)';

    /**
     * Execute the console command.
     */
    public function handle(ConsolidationService $service): int
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            $this->warn('Memory system is disabled. Enable the "Agent Memory" feature flag.');

            return self::SUCCESS;
        }

        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $type = $this->option('type');

        if ($type !== null && ! in_array($type, ['backfill', 'dedup'])) {
            $this->error('Invalid type. Use: backfill, dedup');

            return self::FAILURE;
        }

        $this->info('Starting memory consolidation...');

        if ($userId !== null) {
            $this->line("  User ID: {$userId}");
        }

        if ($type !== null) {
            $this->line("  Type: {$type}");
        }

        try {
            $result = $service->run($userId, $type);

            $this->newLine();
            $this->info('Consolidation completed.');

            if (isset($result['backfill'])) {
                $this->table(
                    ['Backfill Metric', 'Value'],
                    [
                        ['Processed', $result['backfill']['processed'] ?? 0],
                        ['Succeeded', $result['backfill']['succeeded'] ?? 0],
                        ['Failed', $result['backfill']['failed'] ?? 0],
                    ]
                );
            }

            if (isset($result['deduplication'])) {
                $this->table(
                    ['Deduplication Metric', 'Value'],
                    [
                        ['Duplicates Found', $result['deduplication']['duplicates_found'] ?? 0],
                        ['Duplicates Merged', $result['deduplication']['duplicates_merged'] ?? 0],
                    ]
                );
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Consolidation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
