<?php

declare(strict_types=1);

namespace App\Support\Memory;

use App\Models\MemoryEmbedding;
use App\Support\Memory\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hybrid retriever combining pgvector semantic search, PostgreSQL BM25 keyword search,
 * and Neo4j graph traversal with Reciprocal Rank Fusion (RRF) scoring.
 *
 * Three retrieval sources:
 * 1. Semantic search (pgvector) - cosine similarity on embeddings
 * 2. Keyword search (PostgreSQL BM25) - tsvector full-text search
 * 3. Graph traversal (Neo4j) - related entity discovery
 *
 * Graceful degradation:
 * - Missing pgvector: BM25 + graph only
 * - Missing Neo4j: semantic + BM25 only
 * - Missing provider: BM25 only
 * - Source errors: continue with remaining sources
 *
 * RRF fusion formula: score = sum(weight * (1 / (k + rank)))
 * Default k=60 (configurable in config/memory.php)
 */
class HybridRetriever
{
    private ?bool $pgvectorAvailable = null;

    public function __construct(
        private readonly EmbeddingProvider $embeddingProvider,
        private readonly Neo4jGraphStore $graphStore
    ) {}

    /**
     * Retrieve relevant memories using hybrid search with RRF fusion.
     *
     * @param  int  $userId  The user ID to scope results to
     * @param  string  $query  The search query
     * @param  array{
     *     limit?: int,
     *     classification?: array<string>,
     *     semantic_weight?: float,
     *     keyword_weight?: float,
     *     graph_weight?: float
     * }  $options  Search options
     * @return array<array{
     *     id: int|string,
     *     content: string,
     *     source: string,
     *     classification: string,
     *     rrf_score: float,
     *     importance_score?: float
     * }>  Ranked results from available sources
     */
    public function retrieve(int $userId, string $query, array $options = []): array
    {
        $config = config('memory.retrieval', []);
        $k = $config['k'] ?? 60;
        $defaultLimit = $config['default_limit'] ?? 10;
        $maxLimit = $config['max_limit'] ?? 50;

        $limit = min($options['limit'] ?? $defaultLimit, $maxLimit);
        $classifications = $options['classification'] ?? ['public', 'internal'];

        $semanticWeight = $options['semantic_weight'] ?? $config['semantic_weight'] ?? 1.0;
        $keywordWeight = $options['keyword_weight'] ?? $config['keyword_weight'] ?? 1.0;
        $graphWeight = $options['graph_weight'] ?? $config['graph_weight'] ?? 1.0;

        $results = [];
        $sourceResults = [];

        // Source 1: Semantic search (pgvector)
        try {
            if ($this->embeddingProvider->supportsEmbeddings() && $this->isPgvectorAvailable()) {
                $semanticResults = $this->semanticSearch($userId, $query, $classifications, $limit);
                $sourceResults['semantic'] = $semanticResults;
            }
        } catch (\Throwable $e) {
            Log::debug('HybridRetriever: Semantic search failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        // Source 2: BM25 keyword search (PostgreSQL tsvector)
        try {
            $keywordResults = $this->keywordSearch($userId, $query, $classifications, $limit);
            $sourceResults['keyword'] = $keywordResults;
        } catch (\Throwable $e) {
            Log::debug('HybridRetriever: Keyword search failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        // Source 3: Graph traversal (Neo4j)
        try {
            if ($this->graphStore->healthCheck()) {
                $graphResults = $this->graphSearch($userId, $query, $limit);
                $sourceResults['graph'] = $graphResults;
            }
        } catch (\Throwable $e) {
            Log::debug('HybridRetriever: Graph search failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        // Apply RRF fusion
        $results = $this->fuseWithRRF($sourceResults, $k, [
            'semantic' => $semanticWeight,
            'keyword' => $keywordWeight,
            'graph' => $graphWeight,
        ]);

        // Sort by RRF score descending and limit
        usort($results, fn ($a, $b) => $b['rrf_score'] <=> $a['rrf_score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Perform semantic search using pgvector cosine similarity.
     *
     * @return array<array{id: int, content: string, classification: string, score: float}>
     */
    private function semanticSearch(int $userId, string $query, array $classifications, int $limit): array
    {
        $queryEmbedding = $this->embeddingProvider->embed($query);
        if ($queryEmbedding === null) {
            return [];
        }

        $vectorString = '['.implode(',', $queryEmbedding).']';

        // Use cosine similarity with pgvector
        $results = DB::select(
            'SELECT id, content, classification, importance_score,
                    1 - (embedding <=> ?::vector) AS similarity
             FROM memory_embeddings
             WHERE user_id = ?
               AND classification IN ('.$this->placeholders($classifications).')
               AND embedding IS NOT NULL
             ORDER BY similarity DESC
             LIMIT ?',
            array_merge([$vectorString, $userId], $classifications, [$limit])
        );

        return array_map(fn ($row) => [
            'id' => $row->id,
            'content' => $row->content,
            'classification' => $row->classification,
            'importance_score' => $row->importance_score,
            'score' => $row->similarity,
        ], $results);
    }

    /**
     * Perform BM25 keyword search using PostgreSQL tsvector.
     *
     * @return array<array{id: int, content: string, classification: string, score: float}>
     */
    private function keywordSearch(int $userId, string $query, array $classifications, int $limit): array
    {
        // Normalize query for tsvector search
        $tsQuery = $this->normalizeForTsQuery($query);
        if (empty($tsQuery)) {
            return [];
        }

        $results = DB::select(
            "SELECT id, content, classification, importance_score,
                    ts_rank_cd(to_tsvector('english', content), plainto_tsquery('english', ?)) AS rank
             FROM memory_embeddings
             WHERE user_id = ?
               AND classification IN (".$this->placeholders($classifications).")
               AND to_tsvector('english', content) @@ plainto_tsquery('english', ?)
             ORDER BY rank DESC
             LIMIT ?",
            array_merge([$tsQuery, $userId], $classifications, [$tsQuery, $limit])
        );

        return array_map(fn ($row) => [
            'id' => $row->id,
            'content' => $row->content,
            'classification' => $row->classification,
            'importance_score' => $row->importance_score,
            'score' => $row->rank,
        ], $results);
    }

    /**
     * Perform graph traversal search using Neo4j.
     *
     * @return array<array{id: string, content: string, classification: string, score: float}>
     */
    private function graphSearch(int $userId, string $query, int $limit): array
    {
        // Extract potential entity names from query for graph lookup
        $relatedEntities = $this->graphStore->queryRelated($userId, $query, depth: 2);

        return array_map(fn ($entity) => [
            'id' => $entity['id'],
            'content' => sprintf('[%s] %s', $entity['type'], $entity['name']),
            'classification' => 'internal', // Graph entities inherit default classification
            'importance_score' => $entity['importance'] ?? 0.5,
            'score' => 1.0 / ($entity['distance'] + 1), // Closer entities score higher
        ], array_slice($relatedEntities, 0, $limit));
    }

    /**
     * Fuse results from multiple sources using Reciprocal Rank Fusion.
     *
     * RRF formula: score = sum(weight * (1 / (k + rank)))
     *
     * @param  array<string, array>  $sourceResults  Results per source
     * @param  int  $k  RRF constant (default: 60)
     * @param  array<string, float>  $weights  Per-source weights
     * @return array<array{id: int|string, content: string, source: string, classification: string, rrf_score: float}>
     */
    private function fuseWithRRF(array $sourceResults, int $k, array $weights): array
    {
        $fusedScores = [];
        $itemData = [];

        foreach ($sourceResults as $source => $results) {
            $weight = $weights[$source] ?? 1.0;

            foreach ($results as $rank => $item) {
                $itemKey = $this->getItemKey($item);

                if (! isset($fusedScores[$itemKey])) {
                    $fusedScores[$itemKey] = 0.0;
                    $itemData[$itemKey] = [
                        'id' => $item['id'],
                        'content' => $item['content'],
                        'classification' => $item['classification'],
                        'importance_score' => $item['importance_score'] ?? null,
                        'source' => $source,
                    ];
                }

                // RRF score contribution: weight * (1 / (k + rank))
                // Rank is 0-indexed, so add 1 to make it 1-indexed
                $fusedScores[$itemKey] += $weight * (1.0 / ($k + $rank + 1));
            }
        }

        $results = [];
        foreach ($fusedScores as $key => $score) {
            $item = $itemData[$key];
            $item['rrf_score'] = $score;
            $results[] = $item;
        }

        return $results;
    }

    /**
     * Generate a unique key for an item for deduplication.
     */
    private function getItemKey(array $item): string
    {
        // Use content hash for deduplication
        if (isset($item['content'])) {
            return MemoryEmbedding::generateContentHash($item['content']);
        }

        return (string) ($item['id'] ?? uniqid());
    }

    /**
     * Check if pgvector extension is available.
     *
     * Caches result per request to avoid repeated queries.
     */
    protected function isPgvectorAvailable(): bool
    {
        if ($this->pgvectorAvailable !== null) {
            return $this->pgvectorAvailable;
        }

        try {
            $result = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            $this->pgvectorAvailable = $result !== null;
        } catch (\Throwable) {
            $this->pgvectorAvailable = false;
        }

        return $this->pgvectorAvailable;
    }

    /**
     * Normalize a query string for PostgreSQL tsvector search.
     */
    private function normalizeForTsQuery(string $query): string
    {
        // Remove special characters that could break tsquery
        $normalized = preg_replace('/[^\w\s]/', ' ', $query);

        // Collapse whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Generate SQL placeholders for an array.
     */
    private function placeholders(array $array): string
    {
        return implode(',', array_fill(0, count($array), '?'));
    }

    /**
     * Reset cached state (useful for testing).
     */
    public function resetCache(): void
    {
        $this->pgvectorAvailable = null;
    }
}
