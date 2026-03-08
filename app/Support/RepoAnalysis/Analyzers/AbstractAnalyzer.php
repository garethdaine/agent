<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

abstract class AbstractAnalyzer implements AnalyzerInterface
{
    /**
     * @return array<int, string>
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, string>
     */
    protected function snapshotPaths(array $snapshot): array
    {
        $files = data_get($snapshot, 'manifest.files', []);
        if (! is_array($files)) {
            return [];
        }

        $paths = [];
        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            $path = $file['path'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }

            $paths[] = str_replace('\\', '/', $path);
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function snapshotHash(array $snapshot): string
    {
        $snapshotHash = $snapshot['snapshot_hash'] ?? '';

        return is_string($snapshotHash) && $snapshotHash !== ''
            ? $snapshotHash
            : 'snapshot-hash-missing';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function fileContentByPath(array $snapshot, string $path): ?string
    {
        $normalizedTarget = str_replace('\\', '/', $path);
        $files = data_get($snapshot, 'manifest.files', []);
        if (! is_array($files)) {
            return null;
        }

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            $currentPath = $file['path'] ?? null;
            if (! is_string($currentPath)) {
                continue;
            }

            if (str_replace('\\', '/', $currentPath) !== $normalizedTarget) {
                continue;
            }

            $content = $file['content'] ?? null;

            return is_string($content) ? $content : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array{
     *   analyzer_key: string,
     *   analyzer_version: string,
     *   artifact_type: string,
     *   artifact_key: string,
     *   payload: array<string, mixed>,
     *   warnings: array<int, array<string, mixed>>,
     *   warning_artifact_path: string|null,
     *   output_hash: string
     * }
     */
    protected function result(
        array $payload,
        array $warnings = [],
        ?string $warningArtifactPath = null,
    ): array {
        $warnings = $this->normalizeWarnings($warnings);

        $result = [
            'analyzer_key' => $this->key(),
            'analyzer_version' => $this->version(),
            'artifact_type' => $this->key(),
            'artifact_key' => $this->key().'.json',
            'payload' => $payload,
            'warnings' => $warnings,
            'warning_artifact_path' => $warningArtifactPath,
        ];

        $result['output_hash'] = hash(
            'sha256',
            json_encode($this->normalizeForHash($result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
        );

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function normalizeWarnings(array $warnings): array
    {
        foreach ($warnings as &$warning) {
            if (! is_array($warning)) { // @phpstan-ignore function.alreadyNarrowedType
                $warning = ['code' => 'unknown_warning'];
            }
        }
        unset($warning);

        usort($warnings, static function (array $left, array $right): int {
            $leftCode = (string) ($left['code'] ?? '');
            $rightCode = (string) ($right['code'] ?? '');
            $codeComparison = strcmp($leftCode, $rightCode);
            if ($codeComparison !== 0) {
                return $codeComparison;
            }

            $leftMessage = (string) ($left['message'] ?? '');
            $rightMessage = (string) ($right['message'] ?? '');

            return strcmp($leftMessage, $rightMessage);
        });

        return array_values($warnings); // @phpstan-ignore arrayValues.list
    }

    private function isListArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isListArray($value)) {
            $normalized = array_map(fn (mixed $item): mixed => $this->normalizeForHash($item), $value);

            usort($normalized, static fn (mixed $left, mixed $right): int => strcmp(
                json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            ));

            return $normalized;
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeForHash($item);
        }

        return $value;
    }
}
