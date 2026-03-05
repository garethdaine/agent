<?php

declare(strict_types=1);

namespace App\Console\Commands\Memory;

use App\Models\MemoryConsolidationLog;
use App\Models\MemoryConversationLog;
use App\Models\MemoryCoreBlock;
use App\Models\MemoryEmbedding;
use App\Models\MemoryFormationFailure;
use App\Models\MemoryProviderUsage;
use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\Neo4jGraphStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Memory Stats Command.
 *
 * Displays per-layer diagnostics:
 * - Storage sizes
 * - Entity counts
 * - Provider usage
 * - Cost tracking
 *
 * Usage:
 *   php artisan memory:stats
 *   php artisan memory:stats --user=1
 */
class MemoryStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'memory:stats
        {--user= : Scope stats to a specific user ID}';

    /**
     * The console command description.
     */
    protected $description = 'Display memory system statistics and diagnostics';

    /**
     * Execute the console command.
     */
    public function handle(Neo4jGraphStore $graphStore): int
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            $this->warn('Memory system is disabled. Enable the "Agent Memory" feature flag.');

            return self::SUCCESS;
        }

        $userId = $this->option('user') ? (int) $this->option('user') : null;

        $this->info('Memory System Statistics');

        if ($userId !== null) {
            $this->line("  Scoped to User ID: {$userId}");
        }

        $this->newLine();

        // Core Memory Stats (Layer 1)
        $this->displayCoreMemoryStats($userId);

        // Working Memory Stats (Layer 2)
        $this->displayWorkingMemoryStats($userId);

        // Long-term Memory Stats (Layer 3)
        $this->displayLongTermMemoryStats($userId, $graphStore);

        // Formation Failures
        $this->displayFormationStats($userId);

        // Consolidation History
        $this->displayConsolidationStats($userId);

        // Provider Usage
        $this->displayProviderUsage($userId);

        return self::SUCCESS;
    }

    private function displayCoreMemoryStats(?int $userId): void
    {
        $this->info('Layer 1: Core Memory');

        $query = MemoryCoreBlock::query();
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $stats = $query
            ->select('block_type', DB::raw('COUNT(*) as count'))
            ->groupBy('block_type')
            ->get()
            ->pluck('count', 'block_type')
            ->toArray();

        $this->table(
            ['Block Type', 'Count'],
            [
                ['Identity', $stats['identity'] ?? 0],
                ['Operational', $stats['operational'] ?? 0],
                ['Total', array_sum($stats)],
            ]
        );
    }

    private function displayWorkingMemoryStats(?int $userId): void
    {
        $this->info('Layer 2: Working Memory (Redis)');

        try {
            $prefix = config('memory.working_memory.key_prefix', 'memory:run:');
            $keys = Redis::connection('memory')->keys($prefix.'*');

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Active Run Buffers', count($keys)],
                    ['TTL (seconds)', config('memory.working_memory.ttl_seconds', 7200)],
                    ['Max Messages', config('memory.working_memory.max_messages', 15)],
                ]
            );
        } catch (\Throwable $e) {
            $this->warn('Could not connect to Redis: '.$e->getMessage());
        }
    }

    private function displayLongTermMemoryStats(?int $userId, Neo4jGraphStore $graphStore): void
    {
        $this->info('Layer 3: Long-term Memory');

        // Conversation Logs
        $logQuery = MemoryConversationLog::query();
        if ($userId !== null) {
            $logQuery->where('user_id', $userId);
        }
        $logCount = $logQuery->count();

        // Embeddings
        $embQuery = MemoryEmbedding::query();
        if ($userId !== null) {
            $embQuery->where('user_id', $userId);
        }
        $embCount = $embQuery->count();
        $avgDecay = $embQuery->count() > 0
            ? round($embQuery->avg('importance_score') ?? 0, 3)
            : 0;

        // Graph status
        $graphStatus = $graphStore->healthCheck() ? 'Connected' : 'Disconnected';

        $this->table(
            ['Storage', 'Count/Status'],
            [
                ['Conversation Logs', number_format($logCount)],
                ['Embeddings', number_format($embCount)],
                ['Avg Importance Score', $avgDecay],
                ['Neo4j Graph', $graphStatus],
            ]
        );

        // Check pgvector availability
        $pgvectorAvailable = $this->checkPgvectorAvailable();
        $this->line('  pgvector extension: '.($pgvectorAvailable ? 'Available' : 'Not available'));
    }

    private function displayFormationStats(?int $userId): void
    {
        $this->info('Formation Status');

        $query = MemoryFormationFailure::query();
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $pending = (clone $query)->pending()->count();
        $retriable = (clone $query)->retriable()->count();
        $permanent = (clone $query)->permanentlyFailed()->count();
        $backfilled = (clone $query)->backfilled()->count();

        $this->table(
            ['Failure Status', 'Count'],
            [
                ['Pending', $pending],
                ['Retriable', $retriable],
                ['Permanently Failed', $permanent],
                ['Backfilled', $backfilled],
            ]
        );
    }

    private function displayConsolidationStats(?int $userId): void
    {
        $this->info('Recent Consolidations (7 days)');

        $query = MemoryConsolidationLog::query()->recent(7);
        if ($userId !== null) {
            $query->forUser($userId);
        }

        $logs = $query
            ->select('consolidation_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(source_count) as total_processed'))
            ->groupBy('consolidation_type')
            ->get();

        if ($logs->isEmpty()) {
            $this->line('  No recent consolidations.');

            return;
        }

        $this->table(
            ['Type', 'Runs', 'Total Processed'],
            $logs->map(fn ($log) => [
                $log->consolidation_type,
                $log->count,
                $log->total_processed,
            ])->toArray()
        );
    }

    private function displayProviderUsage(?int $userId): void
    {
        $this->info('Provider Usage (30 days)');

        $query = MemoryProviderUsage::query()
            ->where('created_at', '>=', now()->subDays(30));

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $usage = $query
            ->select(
                'provider',
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(input_tokens) as input_tokens'),
                DB::raw('SUM(output_tokens) as output_tokens'),
                DB::raw('SUM(cost_estimate_usd) as cost')
            )
            ->groupBy('provider')
            ->get();

        if ($usage->isEmpty()) {
            $this->line('  No provider usage recorded.');

            return;
        }

        $this->table(
            ['Provider', 'Requests', 'Input Tokens', 'Output Tokens', 'Est. Cost (USD)'],
            $usage->map(fn ($u) => [
                $u->provider,
                number_format($u->requests),
                number_format($u->input_tokens ?? 0),
                number_format($u->output_tokens ?? 0),
                '$'.number_format($u->cost ?? 0, 4),
            ])->toArray()
        );
    }

    private function checkPgvectorAvailable(): bool
    {
        try {
            $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

            return ! empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
