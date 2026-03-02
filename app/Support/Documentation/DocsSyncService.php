<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Jobs\ReindexDocumentationSearchJob;
use App\Models\DocumentationEntry;
use App\Models\DocumentationFragment;
use App\Models\DocumentationLink;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use JsonException;

class DocsSyncService
{
    public function __construct(
        private readonly DocsIngestionPipeline $pipeline,
        private readonly DocsReindexExecutor $reindexExecutor,
        private readonly DocsSyncSleeper $sleeper
    ) {}

    /**
     * @return array{
     *   mode: string,
     *   source: string,
     *   entries_count: int,
     *   fragments_count: int,
     *   links_count: int,
     *   manifest_path: string
     * }
     */
    public function sync(string $mode, string $source): array
    {
        $ingested = $this->pipeline->ingest();

        $manifest = DocumentationEntry::withoutSyncingToSearch(function () use ($ingested, $mode, $source): array {
            return DocumentationFragment::withoutSyncingToSearch(function () use ($ingested, $mode, $source): array {
                return DB::transaction(function () use ($ingested, $mode, $source): array {
                    /** @var array<string, DocumentationEntry> $entriesBySlugLocale */
                    $entriesBySlugLocale = [];
                    $persistedEntryRows = [];
                    $persistedFragmentRows = [];
                    $entryIds = [];
                    $fragmentIds = [];
                    $linksCount = 0;

                    foreach ($ingested['entries'] as $entryData) {
                        $entry = DocumentationEntry::query()->updateOrCreate(
                            [
                                'domain' => (string) $entryData['domain'],
                                'slug' => (string) $entryData['slug'],
                                'locale' => (string) $entryData['locale'],
                            ],
                            [
                                'title' => (string) $entryData['title'],
                                'summary' => (string) $entryData['summary'],
                                'section' => (string) $entryData['section'],
                                'audience' => (string) $entryData['audience'],
                                'status' => (string) $entryData['status'],
                                'version' => (string) $entryData['version'],
                                'tags' => $entryData['tags'],
                                'owner' => (string) $entryData['owner'],
                                'body_markdown' => (string) $entryData['body_markdown'],
                                'body_html' => (string) $entryData['body_html'],
                                'source_path' => (string) $entryData['source_path'],
                                'source_checksum' => (string) $entryData['source_checksum'],
                                'last_reviewed_at' => $entryData['last_reviewed_at'],
                            ]
                        );

                        $entriesBySlugLocale[$this->entryLookupKey($entry->slug, $entry->locale)] = $entry;
                        $entryIds[] = $entry->id;
                        $persistedEntryRows[] = [
                            'domain' => $entry->domain,
                            'slug' => $entry->slug,
                            'locale' => $entry->locale,
                            'source_path' => (string) $entry->source_path,
                            'source_checksum' => (string) $entry->source_checksum,
                        ];
                    }

                    foreach ($ingested['entries'] as $entryData) {
                        $entry = $entriesBySlugLocale[$this->entryLookupKey((string) $entryData['slug'], (string) $entryData['locale'])];
                        $linksCount += $this->syncEntryLinks($entry, $entryData);
                    }

                    foreach ($ingested['fragments'] as $fragmentData) {
                        $learnMoreEntryId = null;
                        $learnMoreSlug = $fragmentData['learn_more_slug'] ?? null;

                        if (is_string($learnMoreSlug) && trim($learnMoreSlug) !== '') {
                            $learnMoreSlug = trim($learnMoreSlug);
                            $lookupKey = $this->entryLookupKey($learnMoreSlug, (string) $fragmentData['locale']);

                            if (isset($entriesBySlugLocale[$lookupKey])) {
                                $learnMoreEntryId = $entriesBySlugLocale[$lookupKey]->id;
                            } else {
                                $entry = DocumentationEntry::query()
                                    ->where('slug', $learnMoreSlug)
                                    ->where('locale', (string) $fragmentData['locale'])
                                    ->first();

                                if ($entry === null) {
                                    throw DocumentationValidationException::fromErrors(
                                        ["Tooltip '{$fragmentData['ui_key']}' references unresolved learn_more_slug '{$learnMoreSlug}'."],
                                        'Docs sync failed'
                                    );
                                }

                                $learnMoreEntryId = $entry->id;
                                $entriesBySlugLocale[$lookupKey] = $entry;
                            }
                        }

                        $fragment = DocumentationFragment::query()->updateOrCreate(
                            [
                                'ui_key' => (string) $fragmentData['ui_key'],
                                'locale' => (string) $fragmentData['locale'],
                            ],
                            [
                                'short_text' => (string) $fragmentData['short_text'],
                                'long_text' => $fragmentData['long_text'],
                                'learn_more_entry_id' => $learnMoreEntryId,
                                'severity' => (string) $fragmentData['severity'],
                                'feature_flag' => $fragmentData['feature_flag'],
                                'status' => (string) $fragmentData['status'],
                                'route_names' => $fragmentData['route_names'],
                                'setting_keys' => $fragmentData['setting_keys'],
                                'source_path' => (string) $fragmentData['source_path'],
                                'source_checksum' => (string) $fragmentData['source_checksum'],
                            ]
                        );

                        $linksCount += $this->syncFragmentLinks($fragment, $fragmentData, $learnMoreEntryId);
                        $fragmentIds[] = $fragment->id;

                        $persistedFragmentRows[] = [
                            'ui_key' => $fragment->ui_key,
                            'locale' => $fragment->locale,
                            'source_path' => (string) $fragment->source_path,
                            'source_checksum' => (string) $fragment->source_checksum,
                            'learn_more_slug' => is_string($learnMoreSlug) ? $learnMoreSlug : null,
                        ];
                    }

                    usort($persistedEntryRows, fn (array $left, array $right): int => [$left['locale'], $left['slug'], $left['domain']] <=> [$right['locale'], $right['slug'], $right['domain']]);
                    usort($persistedFragmentRows, fn (array $left, array $right): int => [$left['locale'], $left['ui_key']] <=> [$right['locale'], $right['ui_key']]);

                    return [
                        'mode' => $mode,
                        'source' => $source,
                        'entries' => $persistedEntryRows,
                        'fragments' => $persistedFragmentRows,
                        'counts' => [
                            'entries' => count($persistedEntryRows),
                            'fragments' => count($persistedFragmentRows),
                            'links' => $linksCount,
                        ],
                        'entry_ids' => array_values(array_unique($entryIds)),
                        'fragment_ids' => array_values(array_unique($fragmentIds)),
                    ];
                });
            });
        });

        $manifestPath = $this->writeManifest(Arr::except($manifest, ['entry_ids', 'fragment_ids']));

        if ($mode === 'deploy') {
            $this->runDeployReindexOrFail(
                $manifest['entry_ids'],
                $manifest['fragment_ids']
            );
        } else {
            ReindexDocumentationSearchJob::dispatch(
                $manifest['entry_ids'],
                $manifest['fragment_ids']
            )->delay(now()->addSeconds((int) config('documentation.search.reindex_delay_seconds', 5)));
        }

        return [
            'mode' => $mode,
            'source' => $source,
            'entries_count' => (int) $manifest['counts']['entries'],
            'fragments_count' => (int) $manifest['counts']['fragments'],
            'links_count' => (int) $manifest['counts']['links'],
            'manifest_path' => $manifestPath,
        ];
    }

    /**
     * @param  array<string, mixed>  $entryData
     */
    private function syncEntryLinks(DocumentationEntry $entry, array $entryData): int
    {
        DocumentationLink::query()
            ->where('documentation_entry_id', $entry->id)
            ->whereNull('documentation_fragment_id')
            ->delete();

        $rows = $this->buildLinkRows(
            $entry->id,
            null,
            $entryData['route_names'] ?? [],
            $entryData['setting_keys'] ?? [],
            $entryData['feature_flags'] ?? []
        );

        foreach ($rows as $row) {
            DocumentationLink::query()->create($row);
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $fragmentData
     */
    private function syncFragmentLinks(DocumentationFragment $fragment, array $fragmentData, ?int $documentationEntryId): int
    {
        DocumentationLink::query()
            ->where('documentation_fragment_id', $fragment->id)
            ->delete();

        if ($documentationEntryId === null) {
            return 0;
        }

        $rows = $this->buildLinkRows(
            $documentationEntryId,
            $fragment->id,
            $fragmentData['route_names'] ?? [],
            $fragmentData['setting_keys'] ?? [],
            $fragmentData['feature_flags'] ?? []
        );

        foreach ($rows as $row) {
            DocumentationLink::query()->create($row);
        }

        return count($rows);
    }

    /**
     * @param  array<int, mixed>  $routeNames
     * @param  array<int, mixed>  $settingKeys
     * @param  array<int, mixed>  $featureFlags
     * @return array<int, array{
     *   documentation_entry_id: int,
     *   documentation_fragment_id: int|null,
     *   route_name: string|null,
     *   setting_key: string|null,
     *   feature_flag: string|null
     * }>
     */
    private function buildLinkRows(
        int $documentationEntryId,
        ?int $documentationFragmentId,
        array $routeNames,
        array $settingKeys,
        array $featureFlags
    ): array {
        $rows = [];

        $routeNames = $this->normalizeStringList($routeNames);
        $settingKeys = $this->normalizeStringList($settingKeys);
        $featureFlags = $this->normalizeStringList($featureFlags);

        foreach ($routeNames as $routeName) {
            $rows[] = [
                'documentation_entry_id' => $documentationEntryId,
                'documentation_fragment_id' => $documentationFragmentId,
                'route_name' => $routeName,
                'setting_key' => null,
                'feature_flag' => null,
            ];
        }

        foreach ($settingKeys as $settingKey) {
            $rows[] = [
                'documentation_entry_id' => $documentationEntryId,
                'documentation_fragment_id' => $documentationFragmentId,
                'route_name' => null,
                'setting_key' => $settingKey,
                'feature_flag' => null,
            ];
        }

        foreach ($featureFlags as $featureFlag) {
            $rows[] = [
                'documentation_entry_id' => $documentationEntryId,
                'documentation_fragment_id' => $documentationFragmentId,
                'route_name' => null,
                'setting_key' => null,
                'feature_flag' => $featureFlag,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizeStringList(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeManifest(array $manifest): string
    {
        $relativePath = trim((string) config('documentation.sync.manifest_path', 'docs-sync/manifest.json'), '/');
        $absolutePath = storage_path('app/'.$relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));

        try {
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to encode docs sync manifest JSON: '.$exception->getMessage(), previous: $exception);
        }

        File::put($absolutePath, $json.PHP_EOL);

        return $absolutePath;
    }

    private function entryLookupKey(string $slug, string $locale): string
    {
        return strtolower(trim($locale)).'|'.strtolower(trim($slug));
    }

    /**
     * @param  array<int, int>  $entryIds
     * @param  array<int, int>  $fragmentIds
     */
    private function runDeployReindexOrFail(array $entryIds, array $fragmentIds): void
    {
        $maxRetries = max(0, (int) config('documentation.sync.deploy.reindex.max_retries', 3));
        $retryIntervalSeconds = max(0, (int) config('documentation.sync.deploy.reindex.retry_interval_seconds', 30));
        $timeoutSeconds = max(1, (int) config('documentation.sync.deploy.reindex.timeout_seconds', 120));

        $attempt = 0;
        $startedAt = microtime(true);
        $lastError = null;

        while (true) {
            $attempt++;

            try {
                $this->reindexExecutor->execute($entryIds, $fragmentIds);

                return;
            } catch (\Throwable $throwable) {
                $lastError = $throwable;
            }

            $retriesUsed = $attempt - 1;
            if ($retriesUsed >= $maxRetries) {
                break;
            }

            $elapsedSeconds = (int) floor(microtime(true) - $startedAt);
            if (($elapsedSeconds + $retryIntervalSeconds) > $timeoutSeconds) {
                break;
            }

            $this->sleeper->sleep($retryIntervalSeconds);
        }

        $message = sprintf(
            'Deploy docs reindex failed after %d attempt(s) with %d retry budget, %ds interval, and %ds timeout cap.',
            $attempt,
            $maxRetries,
            $retryIntervalSeconds,
            $timeoutSeconds
        );

        throw new \RuntimeException($message, previous: $lastError);
    }
}
