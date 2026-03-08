<?php

declare(strict_types=1);

namespace App\Support\Documentation;

interface DocsReindexExecutor
{
    /**
     * @param  array<int, int>  $entryIds
     * @param  array<int, int>  $fragmentIds
     * @param  array<int, int>  $apiArtifactIds
     */
    public function execute(array $entryIds, array $fragmentIds, array $apiArtifactIds = []): void;
}
