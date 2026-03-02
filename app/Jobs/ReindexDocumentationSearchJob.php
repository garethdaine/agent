<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ApiDocArtifact;
use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReindexDocumentationSearchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, int>  $entryIds
     * @param  array<int, int>  $fragmentIds
     * @param  array<int, int>  $apiArtifactIds
     */
    public function __construct(
        public readonly array $entryIds,
        public readonly array $fragmentIds,
        public readonly array $apiArtifactIds = []
    ) {
        $this->onQueue('agent');
    }

    public function handle(): void
    {
        try {
            if ($this->entryIds !== []) {
                DocumentationEntry::query()
                    ->whereIn('id', $this->entryIds)
                    ->get()
                    ->searchable();
            }

            if ($this->fragmentIds !== []) {
                DocumentationFragment::query()
                    ->whereIn('id', $this->fragmentIds)
                    ->get()
                    ->searchable();
            }

            if ($this->apiArtifactIds !== []) {
                ApiDocArtifact::query()
                    ->whereIn('id', $this->apiArtifactIds)
                    ->get()
                    ->searchable();
            }
        } catch (Throwable $throwable) {
            Log::warning('Documentation search reindex job failed.', [
                'entry_ids' => $this->entryIds,
                'fragment_ids' => $this->fragmentIds,
                'api_artifact_ids' => $this->apiArtifactIds,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
