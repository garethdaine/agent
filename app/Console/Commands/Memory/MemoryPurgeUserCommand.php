<?php

declare(strict_types=1);

namespace App\Console\Commands\Memory;

use App\Models\MemoryConsolidationLog;
use App\Models\MemoryConversationLog;
use App\Models\MemoryCoreBlock;
use App\Models\MemoryEmbedding;
use App\Models\MemoryFormationFailure;
use App\Models\MemoryProviderUsage;
use App\Models\MemorySetting;
use App\Models\User;
use App\Support\Memory\Neo4jGraphStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Memory Purge User Command.
 *
 * GDPR compliance: Cascade delete all user memory data including Neo4j cleanup.
 *
 * This is a destructive operation that cannot be undone!
 *
 * Usage:
 *   php artisan memory:purge-user 1
 */
class MemoryPurgeUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'memory:purge-user
        {userId : The user ID to purge all memory data for}
        {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Permanently delete all memory data for a user (GDPR compliance)';

    /**
     * Execute the console command.
     */
    public function handle(Neo4jGraphStore $graphStore): int
    {
        $userId = (int) $this->argument('userId');

        // Verify user exists
        $user = User::find($userId);
        if (! $user) {
            $this->error("User with ID {$userId} not found.");

            return self::FAILURE;
        }

        $this->warn('===== MEMORY DATA PURGE =====');
        $this->line("User ID: {$userId}");
        $this->line("Email: {$user->email}");
        $this->newLine();

        // Show what will be deleted
        $this->displayPurgeSummary($userId, $graphStore);

        $this->newLine();
        $this->error('This action CANNOT be undone!');

        if (! $this->option('force')) {
            if (! $this->confirm('Are you absolutely sure you want to delete ALL memory data for this user?', false)) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }

            // Double confirmation for safety
            $confirmation = $this->ask('Type "DELETE" to confirm:');
            if ($confirmation !== 'DELETE') {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $this->info('Starting purge...');

        try {
            DB::beginTransaction();

            $deleted = $this->purgeUserData($userId, $graphStore);

            DB::commit();

            $this->newLine();
            $this->info('Purge completed successfully.');
            $this->table(
                ['Data Type', 'Records Deleted'],
                array_map(fn ($type, $count) => [$type, $count], array_keys($deleted), array_values($deleted))
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Purge failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Display summary of what will be deleted.
     */
    private function displayPurgeSummary(int $userId, Neo4jGraphStore $graphStore): void
    {
        $this->info('Data to be deleted:');

        $summary = [
            ['Core Blocks', MemoryCoreBlock::where('user_id', $userId)->count()],
            ['Conversation Logs', MemoryConversationLog::where('user_id', $userId)->count()],
            ['Embeddings', MemoryEmbedding::where('user_id', $userId)->count()],
            ['Formation Failures', MemoryFormationFailure::where('user_id', $userId)->count()],
            ['Consolidation Logs', MemoryConsolidationLog::where('user_id', $userId)->count()],
            ['Provider Usage', MemoryProviderUsage::where('user_id', $userId)->count()],
            ['Settings', MemorySetting::where('user_id', $userId)->count()],
            ['Neo4j Graph', $graphStore->healthCheck() ? 'Will be purged' : 'Not available'],
            ['Working Memory (Redis)', $this->countRedisKeys($userId)],
        ];

        $this->table(['Data Type', 'Count/Status'], $summary);
    }

    /**
     * Count Redis working memory keys for user.
     */
    private function countRedisKeys(int $userId): string
    {
        // Working memory is keyed by run_id, not user_id
        // We can't easily count user-specific keys without iterating all runs
        return 'Associated run buffers';
    }

    /**
     * Purge all user data.
     *
     * @return array<string, int> Counts of deleted records
     */
    private function purgeUserData(int $userId, Neo4jGraphStore $graphStore): array
    {
        $deleted = [];

        // Delete from all memory tables
        // Note: Most tables have ON DELETE CASCADE from users table,
        // but we delete explicitly for better control and logging

        $deleted['core_blocks'] = MemoryCoreBlock::where('user_id', $userId)->delete();
        $this->line("  Deleted {$deleted['core_blocks']} core blocks");

        $deleted['conversation_logs'] = MemoryConversationLog::where('user_id', $userId)->delete();
        $this->line("  Deleted {$deleted['conversation_logs']} conversation logs");

        $deleted['embeddings'] = MemoryEmbedding::where('user_id', $userId)->delete();
        $this->line("  Deleted {$deleted['embeddings']} embeddings");

        $deleted['formation_failures'] = MemoryFormationFailure::where('user_id', $userId)->delete();
        $this->line("  Deleted {$deleted['formation_failures']} formation failures");

        $deleted['consolidation_logs'] = MemoryConsolidationLog::where('user_id', $userId)->delete();
        $this->line("  Deleted {$deleted['consolidation_logs']} consolidation logs");

        $deleted['provider_usage'] = MemoryProviderUsage::where('user_id', $userId)->delete();
        $this->line("  Deleted {$deleted['provider_usage']} provider usage records");

        $deleted['settings'] = MemorySetting::where('user_id', $userId)->delete();
        $this->line("  Deleted {$deleted['settings']} settings");

        // Purge Neo4j graph
        if ($graphStore->healthCheck()) {
            $graphStore->purgeUser($userId);
            $deleted['neo4j_entities'] = 1; // We don't get exact count from purgeUser
            $this->line('  Purged Neo4j graph data');
        }

        // Clear Redis working memory for all runs belonging to this user's jobs
        $deleted['redis_keys'] = $this->clearUserRedisKeys($userId);
        $this->line("  Cleared {$deleted['redis_keys']} Redis working memory buffers");

        return $deleted;
    }

    /**
     * Clear Redis working memory keys for user's runs.
     */
    private function clearUserRedisKeys(int $userId): int
    {
        try {
            // Get all run IDs for this user's jobs
            $runIds = DB::table('agent_job_runs')
                ->join('agent_jobs', 'agent_job_runs.job_id', '=', 'agent_jobs.id')
                ->where('agent_jobs.user_id', $userId)
                ->pluck('agent_job_runs.id')
                ->toArray();

            $prefix = config('memory.working_memory.key_prefix', 'memory:run:');
            $deleted = 0;

            foreach ($runIds as $runId) {
                $key = $prefix.$runId.':working';
                if (Redis::connection('memory')->del($key)) {
                    $deleted++;
                }
            }

            return $deleted;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
