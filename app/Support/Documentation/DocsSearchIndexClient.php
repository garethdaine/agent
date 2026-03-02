<?php

declare(strict_types=1);

namespace App\Support\Documentation;

interface DocsSearchIndexClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?string $domain, ?string $section, int $limit): array;
}
