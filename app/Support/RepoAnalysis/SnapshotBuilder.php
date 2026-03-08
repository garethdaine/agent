<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SnapshotBuilder
{
    /**
     * @var array<int, string>
     */
    private array $defaultExcludePaths;

    public function __construct(
        ?array $defaultExcludePaths = null,
        private readonly int $defaultMaxFiles = 50000,
        private readonly int $defaultMaxFileSizeBytes = 5_242_880,
    ) {
        $configuredExcludes = $defaultExcludePaths;
        if ($configuredExcludes === null) {
            $configured = config('repo_analysis.scan.exclude_paths', []);
            $configuredExcludes = is_array($configured) ? $configured : [];
        }

        ['valid' => $normalizedExcludes] = $this->normalizeFilterPaths($configuredExcludes);
        $this->defaultExcludePaths = $normalizedExcludes;
    }

    /**
     * @param  array{
     *   include_paths?: array<int, string>,
     *   exclude_paths?: array<int, string>,
     *   max_files?: int,
     *   max_file_size_bytes?: int
     * }  $options
     * @return array{
     *   snapshot_hash: string,
     *   manifest: array<string, mixed>,
     *   manifest_json: string,
     *   hash_input_json: string
     * }
     */
    public function build(string $projectDirectory, array $options = []): array
    {
        $rootPath = realpath($projectDirectory);
        if (! is_string($rootPath) || ! is_dir($rootPath)) {
            throw new InvalidArgumentException('Project directory must be an existing directory.');
        }

        $rootPath = $this->normalizeAbsolutePath($rootPath);
        $includePathsInput = is_array($options['include_paths'] ?? null) ? $options['include_paths'] : [];
        $excludePathsInput = is_array($options['exclude_paths'] ?? null) ? $options['exclude_paths'] : [];

        $includeNormalization = $this->normalizeFilterPaths($includePathsInput);
        $excludeNormalization = $this->normalizeFilterPaths(array_merge($this->defaultExcludePaths, $excludePathsInput));

        $includePaths = $includeNormalization['valid'];
        $excludePaths = $excludeNormalization['valid'];
        $pathEscapeAttempts = array_values(array_unique(array_merge(
            $includeNormalization['invalid'],
            $excludeNormalization['invalid']
        )));
        sort($pathEscapeAttempts, SORT_STRING);

        $maxFiles = $this->normalizeBoundedInt($options['max_files'] ?? $this->defaultMaxFiles, $this->defaultMaxFiles, 1, 1_000_000);
        $maxFileSizeBytes = $this->normalizeBoundedInt(
            $options['max_file_size_bytes'] ?? $this->defaultMaxFileSizeBytes,
            $this->defaultMaxFileSizeBytes,
            1,
            1_073_741_824,
        );

        $includedFiles = [];
        $skippedEntries = [];
        $discoveredEntries = $this->discoverFileAndSymlinkEntries($rootPath);

        foreach ($discoveredEntries as $relativePath) {
            $absolutePath = $rootPath.'/'.$relativePath;

            if ($this->pathMatchesAnyRule($relativePath, $excludePaths)) {
                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => 'excluded',
                ];

                continue;
            }

            if ($includePaths !== [] && ! $this->pathMatchesAnyRule($relativePath, $includePaths)) {
                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => 'not_included',
                ];

                continue;
            }

            if (is_link($absolutePath)) {
                $resolved = @realpath($absolutePath);
                $reason = is_string($resolved) && ! $this->isWithinRoot($resolved, $rootPath)
                    ? 'path_escape'
                    : 'symlink';

                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => $reason,
                ];

                continue;
            }

            $resolvedPath = @realpath($absolutePath);
            if (! is_string($resolvedPath) || ! $this->isWithinRoot($resolvedPath, $rootPath)) {
                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => 'path_escape',
                ];

                continue;
            }

            if (! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => 'unreadable',
                ];

                continue;
            }

            $size = @filesize($resolvedPath);
            if (! is_int($size)) {
                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => 'unreadable',
                ];

                continue;
            }

            if ($size > $maxFileSizeBytes) {
                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => 'oversize',
                    'size_bytes' => $size,
                ];

                continue;
            }

            if (count($includedFiles) >= $maxFiles) {
                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => 'max_files_guard',
                ];

                continue;
            }

            $contentHash = @hash_file('sha256', $resolvedPath);
            if (! is_string($contentHash) || $contentHash === '') { // @phpstan-ignore identical.alwaysFalse
                $skippedEntries[] = [
                    'path' => $relativePath,
                    'reason' => 'unreadable',
                ];

                continue;
            }

            $mtime = @filemtime($resolvedPath);

            $includedFiles[] = [
                'path' => $relativePath,
                'size_bytes' => $size,
                'content_hash' => $contentHash,
                'mtime' => is_int($mtime) ? $mtime : null,
            ];
        }

        usort($includedFiles, $this->sortEntriesByPathThenReason(...));
        usort($skippedEntries, $this->sortEntriesByPathThenReason(...));

        $manifest = [
            'root_path' => $rootPath,
            'files' => $includedFiles,
            'skipped' => $skippedEntries,
            'guards' => [
                'path_escape_attempts' => $pathEscapeAttempts,
            ],
            'stats' => [
                'discovered_entries' => count($discoveredEntries),
                'included_file_count' => count($includedFiles),
                'skipped_entry_count' => count($skippedEntries),
            ],
            'options' => [
                'include_paths' => $includePaths,
                'exclude_paths' => $excludePaths,
                'max_files' => $maxFiles,
                'max_file_size_bytes' => $maxFileSizeBytes,
            ],
        ];

        $manifestJson = $this->canonicalJsonEncode($manifest);
        $hashInput = $this->buildHashInput($manifest);
        $hashInputJson = $this->canonicalJsonEncode($hashInput);
        $snapshotHash = hash('sha256', $hashInputJson);

        return [
            'snapshot_hash' => $snapshotHash,
            'manifest' => $manifest,
            'manifest_json' => $manifestJson,
            'hash_input_json' => $hashInputJson,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function buildHashInput(array $manifest): array
    {
        $hashInput = $manifest;
        $files = is_array($hashInput['files'] ?? null) ? $hashInput['files'] : [];

        $hashInput['files'] = array_map(static function (mixed $file): array {
            if (! is_array($file)) {
                return [];
            }

            unset($file['mtime']);

            return $file;
        }, $files);

        return $hashInput;
    }

    /**
     * @return array{valid: array<int, string>, invalid: array<int, string>}
     */
    private function normalizeFilterPaths(array $paths): array
    {
        $valid = [];
        $invalid = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $trimmed = trim($path);
            if ($trimmed === '') {
                continue;
            }

            $normalized = str_replace('\\', '/', $trimmed);
            $normalized = ltrim($normalized, '/');
            $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

            while (str_starts_with($normalized, './')) {
                $normalized = substr($normalized, 2);
            }

            if ($normalized === '' || $this->hasPathEscapeSegments($normalized)) {
                $invalid[] = $trimmed;

                continue;
            }

            $isDirectoryRule = str_ends_with($trimmed, '/');
            $normalized = $isDirectoryRule
                ? rtrim($normalized, '/').'/'
                : rtrim($normalized, '/');

            if ($normalized === '') {
                continue;
            }

            $valid[] = $normalized;
        }

        $valid = array_values(array_unique($valid));
        sort($valid, SORT_STRING);

        $invalid = array_values(array_unique($invalid));
        sort($invalid, SORT_STRING);

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    private function hasPathEscapeSegments(string $path): bool
    {
        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $rules
     */
    private function pathMatchesAnyRule(string $path, array $rules): bool
    {
        foreach ($rules as $rule) {
            if (str_ends_with($rule, '/')) {
                if (str_starts_with($path, $rule)) {
                    return true;
                }

                continue;
            }

            if ($path === $rule || str_starts_with($path, $rule.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function discoverFileAndSymlinkEntries(string $rootPath): array
    {
        $entries = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (! $item->isFile() && ! $item->isLink()) {
                continue;
            }

            $absolutePath = str_replace('\\', '/', $item->getPathname());
            $relativePath = ltrim(substr($absolutePath, strlen($rootPath)), '/');

            if ($relativePath === '') {
                continue;
            }

            $entries[] = $relativePath;
        }

        $entries = array_values(array_unique($entries));
        sort($entries, SORT_STRING);

        return $entries;
    }

    private function isWithinRoot(string $candidatePath, string $rootPath): bool
    {
        $normalizedRoot = rtrim($this->normalizeAbsolutePath($rootPath), '/');
        $normalizedCandidate = $this->normalizeAbsolutePath($candidatePath);

        return $normalizedCandidate === $normalizedRoot
            || str_starts_with($normalizedCandidate, $normalizedRoot.'/');
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return rtrim($normalized, '/');
    }

    private function normalizeBoundedInt(mixed $candidate, int $default, int $min, int $max): int
    {
        if (! is_int($candidate) && ! is_numeric($candidate)) {
            return $default;
        }

        $value = (int) $candidate;
        if ($value < $min || $value > $max) {
            return $default;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function sortEntriesByPathThenReason(array $left, array $right): int
    {
        $leftPath = (string) ($left['path'] ?? '');
        $rightPath = (string) ($right['path'] ?? '');

        if ($leftPath === $rightPath) {
            return strcmp((string) ($left['reason'] ?? ''), (string) ($right['reason'] ?? ''));
        }

        return strcmp($leftPath, $rightPath);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonicalJsonEncode(array $payload): string
    {
        return json_encode(
            $this->normalizeForCanonicalJson($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private function normalizeForCanonicalJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->normalizeForCanonicalJson(...), $value);
        }

        $normalized = [];
        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->normalizeForCanonicalJson($item);
        }

        return $normalized;
    }
}
