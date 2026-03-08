<?php

declare(strict_types=1);

namespace App\Support\Memory;

use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;

/**
 * Neo4j Graph Store for Long-term Memory (Layer 3).
 *
 * Stores and queries knowledge graph entities with MERGE for idempotent operations.
 * Supports bi-temporal metadata (valid_from, valid_to, recorded_at) for point-in-time queries.
 *
 * Entity node structure:
 * (:Entity {
 *     id: uuid,
 *     user_id: int,
 *     type: string,
 *     name: string,
 *     classification: string,
 *     importance_score: float,
 *     access_count: int,
 *     created_at: datetime,
 *     updated_at: datetime,
 *     valid_from: datetime,
 *     valid_to: datetime
 * })
 *
 * Connection uses Bolt protocol for efficient graph operations.
 */
class Neo4jGraphStore
{
    private ?ClientInterface $client = null;

    private ?bool $isHealthy = null;

    /**
     * Store entities in Neo4j using MERGE for idempotent creation.
     *
     * @param  int  $userId  The user ID to scope entities to
     * @param  array<array{type: string, name: string, confidence?: float}>  $entities  Entities to store
     */
    public function storeEntities(int $userId, array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        $client = $this->getClient();
        if ($client === null) {
            throw new \RuntimeException('Neo4j client not available');
        }

        $now = (new \DateTimeImmutable)->format(\DateTimeInterface::RFC3339);

        // Use UNWIND for batch operations
        $cypher = <<<'CYPHER'
            UNWIND $entities AS entity
            MERGE (e:Entity {user_id: $userId, type: entity.type, name: entity.name})
            ON CREATE SET
                e.id = randomUUID(),
                e.classification = entity.classification,
                e.importance_score = entity.confidence,
                e.access_count = 0,
                e.created_at = datetime($now),
                e.updated_at = datetime($now),
                e.valid_from = datetime($now),
                e.valid_to = null
            ON MATCH SET
                e.importance_score = CASE
                    WHEN entity.confidence > e.importance_score
                    THEN entity.confidence
                    ELSE e.importance_score
                END,
                e.access_count = e.access_count + 1,
                e.updated_at = datetime($now)
            CYPHER;

        $preparedEntities = [];
        foreach ($entities as $entity) {
            $preparedEntities[] = [
                'type' => $entity['type'] ?? 'Unknown', // @phpstan-ignore nullCoalesce.offset
                'name' => $entity['name'] ?? '', // @phpstan-ignore nullCoalesce.offset
                'confidence' => $entity['confidence'] ?? 0.5,
                'classification' => $entity['classification'] ?? config('memory.default_classification', 'internal'), // @phpstan-ignore nullCoalesce.offset
            ];
        }

        try {
            $client->run($cypher, [
                'userId' => $userId,
                'entities' => $preparedEntities,
                'now' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::error('Neo4jGraphStore: Failed to store entities', [
                'user_id' => $userId,
                'entity_count' => count($entities),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Store relationships between entities in Neo4j.
     *
     * @param  int  $userId  The user ID to scope relationships to
     * @param  array<array{from: string, to: string, type: string}>  $relationships  Relationships to store
     */
    public function storeRelationships(int $userId, array $relationships): void
    {
        if (empty($relationships)) {
            return;
        }

        $client = $this->getClient();
        if ($client === null) {
            throw new \RuntimeException('Neo4j client not available');
        }

        $now = (new \DateTimeImmutable)->format(\DateTimeInterface::RFC3339);

        // Use UNWIND for batch operations
        $cypher = <<<'CYPHER'
            UNWIND $relationships AS rel
            MATCH (from:Entity {user_id: $userId, name: rel.from_name})
            MATCH (to:Entity {user_id: $userId, name: rel.to_name})
            MERGE (from)-[r:RELATED_TO {type: rel.type}]->(to)
            ON CREATE SET
                r.created_at = datetime($now),
                r.weight = 1.0
            ON MATCH SET
                r.weight = r.weight + 0.1,
                r.updated_at = datetime($now)
            CYPHER;

        $preparedRelationships = [];
        foreach ($relationships as $relationship) {
            $preparedRelationships[] = [
                'from_name' => $relationship['from'] ?? '', // @phpstan-ignore nullCoalesce.offset
                'to_name' => $relationship['to'] ?? '', // @phpstan-ignore nullCoalesce.offset
                'type' => $relationship['type'] ?? 'RELATED_TO', // @phpstan-ignore nullCoalesce.offset
            ];
        }

        try {
            $client->run($cypher, [
                'userId' => $userId,
                'relationships' => $preparedRelationships,
                'now' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::error('Neo4jGraphStore: Failed to store relationships', [
                'user_id' => $userId,
                'relationship_count' => count($relationships),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Query related entities for a given entity.
     *
     * @param  int  $userId  The user ID to scope the query to
     * @param  string  $entityId  The entity ID or name to find relations for
     * @param  int  $depth  Maximum traversal depth (default: 2)
     * @return array<array{id: string, type: string, name: string, distance: int}> Related entities
     */
    public function queryRelated(int $userId, string $entityId, int $depth = 2): array
    {
        $client = $this->getClient();
        if ($client === null) {
            return [];
        }

        // Clamp depth to prevent excessive traversals
        $depth = min(max(1, $depth), 5);

        $cypher = <<<CYPHER
            MATCH (start:Entity {user_id: \$userId, name: \$entityId})
            CALL {
                WITH start
                MATCH path = (start)-[*1..$depth]-(related:Entity)
                WHERE related.user_id = \$userId
                RETURN related, length(path) AS distance
            }
            RETURN DISTINCT related.id AS id,
                   related.type AS type,
                   related.name AS name,
                   related.importance_score AS importance,
                   min(distance) AS distance
            ORDER BY distance, importance DESC
            LIMIT 50
            CYPHER;

        try {
            $result = $client->run($cypher, [
                'userId' => $userId,
                'entityId' => $entityId,
            ]);

            $entities = [];
            foreach ($result as $record) {
                $entities[] = [
                    'id' => $record->get('id'),
                    'type' => $record->get('type'),
                    'name' => $record->get('name'),
                    'importance' => $record->get('importance'),
                    'distance' => $record->get('distance'),
                ];
            }

            return $entities;
        } catch (\Throwable $e) {
            Log::warning('Neo4jGraphStore: Query failed', [
                'user_id' => $userId,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get entity count for a user. Returns 0 if Neo4j is unavailable.
     */
    public function getEntityCount(int $userId): int
    {
        if (! $this->healthCheck()) {
            return 0;
        }

        try {
            $client = $this->getClient();
            if ($client === null) {
                return 0;
            }

            $result = $client->run(
                'MATCH (e:Entity {user_id: $userId}) RETURN count(e) AS cnt',
                ['userId' => $userId]
            );

            return (int) ($result->first()->get('cnt') ?? 0);
        } catch (\Throwable $e) {
            Log::debug('Neo4jGraphStore: Entity count failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Check Neo4j connectivity health.
     *
     * Caches result to avoid repeated checks during a single request.
     */
    public function healthCheck(): bool
    {
        if ($this->isHealthy !== null) {
            return $this->isHealthy;
        }

        try {
            $client = $this->getClient();
            if ($client === null) {
                $this->isHealthy = false;

                return false;
            }

            // Simple connectivity check
            $result = $client->run('RETURN 1 AS health');
            $this->isHealthy = $result->first()->get('health') === 1;

            return $this->isHealthy;
        } catch (\Throwable $e) {
            Log::debug('Neo4jGraphStore: Health check failed', [
                'error' => $e->getMessage(),
            ]);
            $this->isHealthy = false;

            return false;
        }
    }

    /**
     * Delete all entities for a user (GDPR compliance).
     *
     * @param  int  $userId  The user ID to purge
     */
    public function purgeUser(int $userId): void
    {
        $client = $this->getClient();
        if ($client === null) {
            return;
        }

        try {
            // Delete all relationships first, then entities
            $client->run('MATCH (e:Entity {user_id: $userId})-[r]-() DELETE r', [
                'userId' => $userId,
            ]);
            $client->run('MATCH (e:Entity {user_id: $userId}) DELETE e', [
                'userId' => $userId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Neo4jGraphStore: Failed to purge user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get or create the Neo4j client instance.
     */
    private function getClient(): ?ClientInterface
    {
        if ($this->client !== null) {
            return $this->client;
        }

        try {
            $host = config('memory.neo4j.host', 'localhost');
            $port = config('memory.neo4j.port', 7687);
            $username = config('memory.neo4j.username', 'neo4j');
            $password = config('memory.neo4j.password', 'password');

            $uri = sprintf('bolt://%s:%s@%s:%d', $username, $password, $host, $port);

            $this->client = ClientBuilder::create()
                ->withDriver('default', $uri)
                ->withDefaultDriver('default')
                ->build();

            return $this->client;
        } catch (\Throwable $e) {
            Log::debug('Neo4jGraphStore: Failed to create client', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Reset cached state (useful for testing).
     */
    public function resetCache(): void
    {
        $this->isHealthy = null;
        $this->client = null;
    }

    /**
     * Prune entities below importance threshold after retention period.
     *
     * @param  int|null  $userId  Scope to specific user, null for all
     * @param  float  $threshold  Importance threshold (prune below this)
     * @param  int  $retentionDays  Only prune entities older than this
     * @param  bool  $dryRun  If true, only count what would be pruned
     * @return array{pruned?: int, would_prune?: int} Prune results
     */
    public function pruneEntities(?int $userId, float $threshold, int $retentionDays, bool $dryRun): array
    {
        $client = $this->getClient();
        if ($client === null) {
            return ['pruned' => 0, 'would_prune' => 0];
        }

        $cutoffDate = (new \DateTimeImmutable("-{$retentionDays} days"))->format(\DateTimeInterface::RFC3339);

        try {
            if ($dryRun) {
                // Count what would be pruned
                $cypher = <<<'CYPHER'
                    MATCH (e:Entity)
                    WHERE ($userId IS NULL OR e.user_id = $userId)
                      AND e.importance_score < $threshold
                      AND e.created_at < datetime($cutoffDate)
                    RETURN count(e) AS count
                    CYPHER;

                $result = $client->run($cypher, [
                    'userId' => $userId,
                    'threshold' => $threshold,
                    'cutoffDate' => $cutoffDate,
                ]);

                return ['would_prune' => $result->first()->get('count') ?? 0];
            }

            // Actually prune - delete relationships first, then entities
            $cypher = <<<'CYPHER'
                MATCH (e:Entity)
                WHERE ($userId IS NULL OR e.user_id = $userId)
                  AND e.importance_score < $threshold
                  AND e.created_at < datetime($cutoffDate)
                WITH e
                OPTIONAL MATCH (e)-[r]-()
                DELETE r
                WITH e
                DELETE e
                RETURN count(e) AS count
                CYPHER;

            $result = $client->run($cypher, [
                'userId' => $userId,
                'threshold' => $threshold,
                'cutoffDate' => $cutoffDate,
            ]);

            $pruned = $result->first()->get('count') ?? 0;

            if ($pruned > 0) {
                Log::info('Neo4jGraphStore: Pruned entities', [
                    'user_id' => $userId,
                    'pruned' => $pruned,
                    'threshold' => $threshold,
                    'retention_days' => $retentionDays,
                ]);
            }

            return ['pruned' => $pruned];
        } catch (\Throwable $e) {
            Log::error('Neo4jGraphStore: Failed to prune entities', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
