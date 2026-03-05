<?php

declare(strict_types=1);

namespace App\Console\Commands\Memory;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\ForgettingService;
use Illuminate\Console\Command;

/**
 * Memory Prune Command.
 *
 * Runs tiered pruning of memory data:
 * - Embeddings below decay threshold (0.1 default)
 * - Graph entities below importance threshold (0.2 default) after 90 days
 *
 * Default mode is DRY-RUN (preview only). Use --force to actually delete.
 *
 * Usage:
 *   php artisan memory:prune                  # Dry-run for all users
 *   php artisan memory:prune --user=1         # Dry-run for user 1
 *   php artisan memory:prune --force          # Actually delete
 *   php artisan memory:prune --execute        # Actually delete (alias)
 */
class MemoryPruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'memory:prune
        {--user= : Scope pruning to a specific user ID}
        {--force : Actually delete (not dry-run)}
        {--execute : Actually delete (alias for --force)}';

    /**
     * The console command description.
     */
    protected $description = 'Prune memory data below decay thresholds (dry-run by default, use --force to delete)';

    /**
     * Execute the console command.
     */
    public function handle(ForgettingService $service): int
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            $this->warn('Memory system is disabled. Enable the "Agent Memory" feature flag.');

            return self::SUCCESS;
        }

        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $dryRun = ! ($this->option('force') || $this->option('execute'));

        if ($dryRun) {
            $this->info('DRY-RUN MODE: No data will be deleted.');
            $this->line('Use --force or --execute to actually delete.');
            $this->newLine();
        } else {
            $this->warn('LIVE MODE: Data will be permanently deleted.');

            if (! $this->confirm('Are you sure you want to proceed?', false)) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $this->info('Starting memory prune...');

        if ($userId !== null) {
            $this->line("  User ID: {$userId}");
        }

        $this->line('  Embedding decay threshold: '.config('memory.forgetting.embedding_decay_threshold', 0.1));
        $this->line('  Graph importance threshold: '.config('memory.forgetting.graph_importance_threshold', 0.2));
        $this->line('  Graph retention days: '.config('memory.forgetting.graph_retention_days', 90));

        try {
            $result = $service->prune($userId, $dryRun);

            $this->newLine();

            if ($dryRun) {
                $this->info('Preview completed. Would prune:');
                $this->table(
                    ['Category', 'Count'],
                    [
                        ['Embeddings', $result['embeddings_would_prune'] ?? 0],
                        ['Graph Entities', $result['graph_entities_would_prune'] ?? 0],
                    ]
                );

                if (! empty($result['candidates'])) {
                    $this->newLine();
                    $this->info('Embedding candidates for pruning:');
                    $this->table(
                        ['ID', 'Content Preview', 'Decay Score', 'Importance', 'Last Accessed'],
                        array_map(fn ($c) => [
                            $c['id'],
                            mb_substr($c['content_preview'], 0, 30),
                            $c['decay_score'],
                            $c['importance_score'],
                            $c['last_accessed'] ?? 'Never',
                        ], array_slice($result['candidates'], 0, 10))
                    );

                    if (count($result['candidates']) > 10) {
                        $this->line('  ... and '.(count($result['candidates']) - 10).' more');
                    }
                }
            } else {
                $this->info('Prune completed.');
                $this->table(
                    ['Category', 'Deleted'],
                    [
                        ['Embeddings', $result['embeddings_pruned'] ?? 0],
                        ['Graph Entities', $result['graph_entities_pruned'] ?? 0],
                    ]
                );
            }

            if (! empty($result['graph_skipped'])) {
                $this->warn('Graph pruning skipped: '.($result['graph_reason'] ?? 'Unknown reason'));
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Prune failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
