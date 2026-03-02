<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enables pgvector extension for semantic search.
 *
 * This migration is idempotent and non-fatal.
 * If pgvector is not available, the system gracefully degrades to BM25-only search.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            Log::info('pgvector extension enabled successfully');
        } catch (\Throwable $e) {
            // Extension not available, system degrades gracefully to BM25
            Log::info('pgvector extension not available, semantic search disabled', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        // We don't drop the extension on rollback as other tables may depend on it
        // and it's safer to leave it in place
    }
};
