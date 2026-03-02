<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Creates memory_embeddings table for Long-term Memory (Layer 3).
 *
 * Stores text embeddings with content_hash for deduplication.
 * Vector column added conditionally if pgvector is available.
 * BM25 tsvector index always created for keyword search.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 30);
            $table->string('source_id', 100);
            $table->text('content');
            $table->string('content_hash', 64);
            $table->jsonb('metadata_json')->nullable();
            $table->string('classification', 20)->default('internal');
            $table->float('importance_score')->default(0.5);
            $table->integer('access_count')->default(0);
            $table->timestampTz('last_accessed_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'classification'], 'memory_embeddings_user_classification_idx');
            $table->index('content_hash', 'memory_embeddings_content_hash_index');
        });

        // Conditional vector column and HNSW index (requires pgvector)
        try {
            DB::statement('ALTER TABLE memory_embeddings ADD COLUMN embedding vector(1536)');
            DB::statement('CREATE INDEX memory_embeddings_embedding_idx ON memory_embeddings USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 128)');
        } catch (\Throwable $e) {
            // pgvector not available, graceful degradation to BM25-only
            Log::info('pgvector extension not available for memory_embeddings, semantic search disabled', [
                'error' => $e->getMessage(),
            ]);
        }

        // BM25 tsvector index (always available)
        DB::statement("CREATE INDEX memory_embeddings_content_fts ON memory_embeddings USING GIN (to_tsvector('english', content))");
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_embeddings');
    }
};
