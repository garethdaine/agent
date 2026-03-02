<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Support\Documentation\Ingestion\MarkdownFrontMatterParser;
use App\Support\Documentation\Ingestion\TooltipYamlParser;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use DateTimeInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocsIngestionPipeline
{
    public function __construct(
        private readonly MarkdownFrontMatterParser $markdownParser,
        private readonly TooltipYamlParser $tooltipParser
    ) {}

    /**
     * @return array{
     *   entries: array<int, array<string, mixed>>,
     *   fragments: array<int, array<string, mixed>>
     * }
     */
    public function ingest(): array
    {
        $entries = [];
        $fragments = [];
        $errors = [];

        $this->collectMarkdownEntries(
            'product',
            'product_doc',
            (string) config('documentation.paths.product'),
            $entries,
            $errors
        );
        $this->collectMarkdownEntries(
            'api',
            'api_doc',
            (string) config('documentation.paths.api'),
            $entries,
            $errors
        );
        $this->collectTooltipFragments(
            'tooltips',
            (string) config('documentation.paths.tooltips'),
            $fragments,
            $errors
        );

        $this->appendDuplicateKeyErrors($entries, $fragments, $errors);

        if ($errors !== []) {
            throw DocumentationValidationException::fromErrors($errors, 'Docs ingestion failed');
        }

        usort($entries, function (array $left, array $right): int {
            return [$left['locale'], $left['slug'], $left['domain'], $left['source_path']]
                <=> [$right['locale'], $right['slug'], $right['domain'], $right['source_path']];
        });
        usort($fragments, function (array $left, array $right): int {
            return [$left['locale'], $left['ui_key'], $left['source_path']]
                <=> [$right['locale'], $right['ui_key'], $right['source_path']];
        });

        return [
            'entries' => $entries,
            'fragments' => $fragments,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<int, string>  $errors
     */
    private function collectMarkdownEntries(
        string $scope,
        string $domain,
        string $rootPath,
        array &$entries,
        array &$errors
    ): void {
        if (! File::isDirectory($rootPath)) {
            $errors[] = "Docs {$scope} directory does not exist: {$rootPath}.";

            return;
        }

        foreach ($this->findFilesByExtensions($rootPath, ['md', 'markdown']) as $filePath) {
            $sourcePath = $this->toScopedSourcePath($scope, $rootPath, $filePath);

            try {
                $parsed = $this->markdownParser->parse((string) File::get($filePath), $sourcePath);
            } catch (DocumentationValidationException $exception) {
                foreach ($exception->errors() as $error) {
                    $errors[] = "{$sourcePath}: {$error}";
                }

                continue;
            }

            /** @var array<string, mixed> $frontMatter */
            $frontMatter = $parsed['front_matter'];
            $bodyMarkdown = (string) ($parsed['body'] ?? '');
            $entries[] = [
                'domain' => $domain,
                'slug' => (string) $frontMatter['slug'],
                'locale' => (string) $frontMatter['locale'],
                'title' => (string) $frontMatter['title'],
                'summary' => (string) $frontMatter['summary'],
                'section' => (string) $frontMatter['section'],
                'audience' => (string) $frontMatter['audience'],
                'status' => (string) $frontMatter['status'],
                'version' => (string) $frontMatter['version'],
                'tags' => $this->normalizeStringList($frontMatter['tags'] ?? []),
                'owner' => (string) $frontMatter['owner'],
                'route_names' => $this->normalizeStringList($frontMatter['route_names'] ?? []),
                'setting_keys' => $this->normalizeStringList($frontMatter['setting_keys'] ?? []),
                'feature_flags' => $this->normalizeStringList($frontMatter['feature_flags'] ?? []),
                'body_markdown' => $bodyMarkdown,
                'body_html' => Str::markdown($bodyMarkdown),
                'source_path' => $sourcePath,
                'source_checksum' => hash_file('sha256', $filePath) ?: hash('sha256', (string) File::get($filePath)),
                'last_reviewed_at' => $this->normalizeReviewedAt($frontMatter['reviewed_at'] ?? null),
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fragments
     * @param  array<int, string>  $errors
     */
    private function collectTooltipFragments(
        string $scope,
        string $rootPath,
        array &$fragments,
        array &$errors
    ): void {
        if (! File::isDirectory($rootPath)) {
            $errors[] = "Docs {$scope} directory does not exist: {$rootPath}.";

            return;
        }

        foreach ($this->findFilesByExtensions($rootPath, ['yaml', 'yml']) as $filePath) {
            $sourcePath = $this->toScopedSourcePath($scope, $rootPath, $filePath);

            try {
                $parsed = $this->tooltipParser->parse((string) File::get($filePath), $sourcePath);
            } catch (DocumentationValidationException $exception) {
                foreach ($exception->errors() as $error) {
                    $errors[] = "{$sourcePath}: {$error}";
                }

                continue;
            }

            $sourceChecksum = hash_file('sha256', $filePath) ?: hash('sha256', (string) File::get($filePath));
            foreach ($parsed as $fragment) {
                /** @var array<string, mixed> $fragment */
                $metadata = is_array($fragment['metadata'] ?? null) ? $fragment['metadata'] : [];
                $featureFlags = $this->normalizeStringList($fragment['feature_flags'] ?? []);

                if ($featureFlags === [] && is_string($fragment['feature_flag'] ?? null) && trim((string) $fragment['feature_flag']) !== '') {
                    $featureFlags = [trim((string) $fragment['feature_flag'])];
                }

                $learnMoreSlug = $fragment['learn_more_slug'] ?? null;
                if (! is_string($learnMoreSlug) || trim($learnMoreSlug) === '') {
                    $learnMoreSlug = null;
                } else {
                    $learnMoreSlug = trim($learnMoreSlug);
                }

                $fragments[] = [
                    'ui_key' => (string) $fragment['ui_key'],
                    'locale' => (string) ($metadata['locale'] ?? config('documentation.locale.default', 'en')),
                    'short_text' => (string) $fragment['short_text'],
                    'long_text' => is_string($fragment['long_text'] ?? null) ? trim((string) $fragment['long_text']) : null,
                    'severity' => (string) $fragment['severity'],
                    'status' => is_string($fragment['status'] ?? null) ? trim((string) $fragment['status']) : 'published',
                    'learn_more_slug' => $learnMoreSlug,
                    'feature_flag' => $featureFlags[0] ?? null,
                    'route_names' => $this->normalizeStringList($fragment['route_names'] ?? []),
                    'setting_keys' => $this->normalizeStringList($fragment['setting_keys'] ?? []),
                    'feature_flags' => $featureFlags,
                    'source_path' => $sourcePath,
                    'source_checksum' => $sourceChecksum,
                ];
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<int, array<string, mixed>>  $fragments
     * @param  array<int, string>  $errors
     */
    private function appendDuplicateKeyErrors(array $entries, array $fragments, array &$errors): void
    {
        $entrySourcesByKey = [];
        foreach ($entries as $entry) {
            $key = strtolower((string) $entry['locale']).'|'.strtolower((string) $entry['slug']);
            $entrySourcesByKey[$key] ??= [];
            $entrySourcesByKey[$key][] = (string) $entry['source_path'];
        }

        foreach ($entrySourcesByKey as $key => $sources) {
            if (count($sources) <= 1) {
                continue;
            }

            sort($sources);
            $errors[] = "Duplicate documentation slug key '{$key}' found in files: ".implode(', ', $sources).'.';
        }

        $fragmentSourcesByKey = [];
        foreach ($fragments as $fragment) {
            $key = strtolower((string) $fragment['locale']).'|'.strtolower((string) $fragment['ui_key']);
            $fragmentSourcesByKey[$key] ??= [];
            $fragmentSourcesByKey[$key][] = (string) $fragment['source_path'];
        }

        foreach ($fragmentSourcesByKey as $key => $sources) {
            if (count($sources) <= 1) {
                continue;
            }

            sort($sources);
            $errors[] = "Duplicate tooltip ui_key '{$key}' found in files: ".implode(', ', $sources).'.';
        }
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
     * @param  array<int, string>  $extensions
     * @return array<int, string>
     */
    private function findFilesByExtensions(string $directory, array $extensions): array
    {
        $normalizedExtensions = array_map(
            static fn (string $extension): string => strtolower(ltrim($extension, '.')),
            $extensions
        );

        $files = [];
        foreach (File::allFiles($directory) as $file) {
            if (in_array(strtolower($file->getExtension()), $normalizedExtensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function toScopedSourcePath(string $scope, string $scopeRoot, string $absolutePath): string
    {
        $relative = ltrim(str_replace($scopeRoot, '', $absolutePath), DIRECTORY_SEPARATOR);

        return trim($scope.'/'.$relative, '/');
    }

    private function normalizeReviewedAt(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d 00:00:00');
        }

        if (is_int($value)) {
            return gmdate('Y-m-d 00:00:00', $value);
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $trimmed) === 1) {
            return gmdate('Y-m-d 00:00:00', (int) $trimmed);
        }

        $timestamp = strtotime($trimmed);

        return $timestamp !== false
            ? gmdate('Y-m-d 00:00:00', $timestamp)
            : null;
    }
}
