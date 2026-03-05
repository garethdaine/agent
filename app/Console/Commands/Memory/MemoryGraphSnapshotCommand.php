<?php

declare(strict_types=1);

namespace App\Console\Commands\Memory;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Memory\Neo4jGraphStore;
use Illuminate\Console\Command;
use Laudis\Neo4j\ClientBuilder;

/**
 * Memory Graph Snapshot Command.
 *
 * Point-in-time inspection of knowledge graph using bi-temporal metadata.
 *
 * Usage:
 *   php artisan memory:graph-snapshot 1
 *   php artisan memory:graph-snapshot 1 --output=snapshot.json
 */
class MemoryGraphSnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'memory:graph-snapshot
        {userId : The user ID to snapshot}
        {--output= : Output file path (default: stdout)}
        {--format=json : Output format: json, table}
        {--limit=100 : Maximum entities to return}';

    /**
     * The console command description.
     */
    protected $description = 'Generate a point-in-time snapshot of the knowledge graph for a user';

    /**
     * Execute the console command.
     */
    public function handle(Neo4jGraphStore $graphStore): int
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::MEMORY_ENABLED)) {
            $this->warn('Memory system is disabled. Enable the "Agent Memory" feature flag.');

            return self::SUCCESS;
        }

        $userId = (int) $this->argument('userId');
        $output = $this->option('output');
        $format = $this->option('format');
        $limit = (int) $this->option('limit');

        if (! $graphStore->healthCheck()) {
            $this->error('Neo4j is not available.');

            return self::FAILURE;
        }

        $this->info("Generating graph snapshot for user {$userId}...");

        try {
            $snapshot = $this->getGraphSnapshot($userId, $limit);

            if ($format === 'table') {
                $this->displayAsTable($snapshot);
            } else {
                $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                if ($output) {
                    file_put_contents($output, $json);
                    $this->info("Snapshot written to: {$output}");
                } else {
                    $this->line($json);
                }
            }

            $this->newLine();
            $this->info("Total entities: {$snapshot['summary']['entity_count']}");
            $this->info("Total relationships: {$snapshot['summary']['relationship_count']}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to generate snapshot: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Get graph snapshot data.
     *
     * @return array<string, mixed>
     */
    private function getGraphSnapshot(int $userId, int $limit): array
    {
        $host = config('memory.neo4j.host', 'localhost');
        $port = config('memory.neo4j.port', 7687);
        $username = config('memory.neo4j.username', 'neo4j');
        $password = config('memory.neo4j.password', 'password');

        $uri = sprintf('bolt://%s:%s@%s:%d', $username, $password, $host, $port);

        $client = ClientBuilder::create()
            ->withDriver('default', $uri)
            ->withDefaultDriver('default')
            ->build();

        // Get entities
        $entityCypher = <<<'CYPHER'
            MATCH (e:Entity {user_id: $userId})
            RETURN e.id AS id,
                   e.type AS type,
                   e.name AS name,
                   e.classification AS classification,
                   e.importance_score AS importance,
                   e.access_count AS access_count,
                   e.created_at AS created_at,
                   e.valid_from AS valid_from,
                   e.valid_to AS valid_to
            ORDER BY e.importance_score DESC
            LIMIT $limit
            CYPHER;

        $entityResult = $client->run($entityCypher, [
            'userId' => $userId,
            'limit' => $limit,
        ]);

        $entities = [];
        foreach ($entityResult as $record) {
            $entities[] = [
                'id' => $record->get('id'),
                'type' => $record->get('type'),
                'name' => $record->get('name'),
                'classification' => $record->get('classification'),
                'importance' => $record->get('importance'),
                'access_count' => $record->get('access_count'),
                'created_at' => $record->get('created_at')?->format('Y-m-d H:i:s'),
                'valid_from' => $record->get('valid_from')?->format('Y-m-d H:i:s'),
                'valid_to' => $record->get('valid_to')?->format('Y-m-d H:i:s'),
            ];
        }

        // Get relationships
        $relCypher = <<<'CYPHER'
            MATCH (a:Entity {user_id: $userId})-[r:RELATED_TO]->(b:Entity {user_id: $userId})
            RETURN a.name AS from,
                   b.name AS to,
                   r.type AS type,
                   r.weight AS weight
            LIMIT $limit
            CYPHER;

        $relResult = $client->run($relCypher, [
            'userId' => $userId,
            'limit' => $limit,
        ]);

        $relationships = [];
        foreach ($relResult as $record) {
            $relationships[] = [
                'from' => $record->get('from'),
                'to' => $record->get('to'),
                'type' => $record->get('type'),
                'weight' => $record->get('weight'),
            ];
        }

        // Get counts
        $countCypher = <<<'CYPHER'
            MATCH (e:Entity {user_id: $userId})
            WITH count(e) AS entity_count
            OPTIONAL MATCH (:Entity {user_id: $userId})-[r:RELATED_TO]->(:Entity {user_id: $userId})
            RETURN entity_count, count(r) AS relationship_count
            CYPHER;

        $countResult = $client->run($countCypher, ['userId' => $userId]);
        $counts = $countResult->first();

        return [
            'user_id' => $userId,
            'snapshot_time' => now()->toIso8601String(),
            'entities' => $entities,
            'relationships' => $relationships,
            'summary' => [
                'entity_count' => $counts?->get('entity_count') ?? 0,
                'relationship_count' => $counts?->get('relationship_count') ?? 0,
                'entity_types' => array_count_values(array_column($entities, 'type')),
            ],
        ];
    }

    /**
     * Display snapshot as table.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function displayAsTable(array $snapshot): void
    {
        $this->info('Entities:');
        if (empty($snapshot['entities'])) {
            $this->line('  No entities found.');
        } else {
            $this->table(
                ['Type', 'Name', 'Importance', 'Access Count', 'Classification'],
                array_map(fn ($e) => [
                    $e['type'],
                    mb_substr($e['name'], 0, 40),
                    round($e['importance'] ?? 0, 3),
                    $e['access_count'] ?? 0,
                    $e['classification'] ?? 'internal',
                ], $snapshot['entities'])
            );
        }

        $this->newLine();
        $this->info('Relationships:');
        if (empty($snapshot['relationships'])) {
            $this->line('  No relationships found.');
        } else {
            $this->table(
                ['From', 'To', 'Type', 'Weight'],
                array_map(fn ($r) => [
                    mb_substr($r['from'], 0, 30),
                    mb_substr($r['to'], 0, 30),
                    $r['type'],
                    round($r['weight'] ?? 0, 2),
                ], $snapshot['relationships'])
            );
        }

        $this->newLine();
        $this->info('Entity Type Distribution:');
        foreach ($snapshot['summary']['entity_types'] as $type => $count) {
            $this->line("  {$type}: {$count}");
        }
    }
}
