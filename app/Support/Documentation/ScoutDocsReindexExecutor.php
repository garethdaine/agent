<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Models\ApiDocArtifact;
use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;

class ScoutDocsReindexExecutor implements DocsReindexExecutor
{
    /**
     * @param  array<int, int>  $entryIds
     * @param  array<int, int>  $fragmentIds
     * @param  array<int, int>  $apiArtifactIds
     */
    public function execute(array $entryIds, array $fragmentIds, array $apiArtifactIds = []): void
    {
        if ($entryIds !== []) {
            DocumentationEntry::query()
                ->whereIn('id', $entryIds)
                ->get()
                ->searchable();
        }

        if ($fragmentIds !== []) {
            DocumentationFragment::query()
                ->whereIn('id', $fragmentIds)
                ->get()
                ->searchable();
        }

        if ($apiArtifactIds !== []) {
            ApiDocArtifact::query()
                ->whereIn('id', $apiArtifactIds)
                ->get()
                ->searchable();
        }
    }
}

