<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Jobs\ReindexDocumentationSearchJob;
use App\Models\ApiDocArtifact;
use App\Models\DocumentationEntry;
use App\Support\Documentation\Schemas\DocumentationValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class OpenApiArtifactIngestService
{
    /**
     * @return array{
     *   artifact_path: string,
     *   operations_count: int,
     *   spec_version: string|null,
     *   spec_checksum: string
     * }
     */
    public function ingest(?string $artifactPath = null): array
    {
        $resolvedPath = $this->resolveArtifactPath($artifactPath);
        $rawSpec = $this->readArtifact($resolvedPath);
        $spec = $this->parseArtifact($rawSpec, $resolvedPath);

        $specChecksum = hash('sha256', $rawSpec);
        $specVersion = $this->normalizeNullableString($spec['info']['version'] ?? null);
        $linkedSlugsExtension = (string) config('documentation.openapi.linked_doc_slugs_extension', 'x-linked-doc-slugs');

        $operations = $this->extractOperations($spec, $linkedSlugsExtension);
        $slugToEntryId = $this->resolveLinkedSlugs($operations);

        $artifactIds = ApiDocArtifact::withoutSyncingToSearch(function () use (
            $operations,
            $slugToEntryId,
            $specChecksum,
            $specVersion
        ): array {
            return DB::transaction(function () use (
                $operations,
                $slugToEntryId,
                $specChecksum,
                $specVersion
            ): array {
                $apiArtifactIds = [];

                foreach ($operations as $operation) {
                    $primarySlug = $operation['linked_doc_slugs'][0];

                    $artifact = ApiDocArtifact::query()->updateOrCreate(
                        ['operation_id' => $operation['operation_id']],
                        [
                            'documentation_entry_id' => $slugToEntryId[$primarySlug],
                            'domain' => 'api_doc',
                            'http_method' => $operation['http_method'],
                            'path' => $operation['path'],
                            'summary' => $operation['summary'],
                            'description' => $operation['description'],
                            'section' => $operation['section'],
                            'tags' => $operation['tags'],
                            'spec_version' => $specVersion,
                            'spec_checksum' => $specChecksum,
                            'linked_doc_slugs' => $operation['linked_doc_slugs'],
                            'published_at' => now(),
                        ]
                    );

                    $apiArtifactIds[] = $artifact->id;
                }

                return array_values(array_unique($apiArtifactIds));
            });
        });

        if ($artifactIds !== []) {
            ReindexDocumentationSearchJob::dispatch(
                entryIds: [],
                fragmentIds: [],
                apiArtifactIds: $artifactIds
            )->delay(now()->addSeconds((int) config('documentation.search.reindex_delay_seconds', 5)));
        }

        return [
            'artifact_path' => $resolvedPath,
            'operations_count' => count($operations),
            'spec_version' => $specVersion,
            'spec_checksum' => $specChecksum,
        ];
    }

    private function resolveArtifactPath(?string $artifactPath): string
    {
        $candidate = $this->normalizeNullableString($artifactPath)
            ?? $this->normalizeNullableString((string) config('documentation.openapi.artifact_path'));

        if ($candidate === null) {
            throw DocumentationValidationException::fromErrors(
                ['OpenAPI artifact path is not configured.'],
                'OpenAPI ingest failed'
            );
        }

        if (! str_starts_with($candidate, DIRECTORY_SEPARATOR)) {
            $candidate = base_path($candidate);
        }

        return $candidate;
    }

    private function readArtifact(string $path): string
    {
        if (! File::exists($path)) {
            throw DocumentationValidationException::fromErrors(
                ["OpenAPI artifact file does not exist: {$path}."],
                'OpenAPI ingest failed'
            );
        }

        $contents = (string) File::get($path);
        if (trim($contents) === '') {
            throw DocumentationValidationException::fromErrors(
                ["OpenAPI artifact file is empty: {$path}."],
                'OpenAPI ingest failed'
            );
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseArtifact(string $rawSpec, string $path): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($rawSpec, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (JsonException) {
            // Fall through to YAML parse.
        }

        try {
            /** @var mixed $decoded */
            $decoded = Yaml::parse($rawSpec);
        } catch (ParseException $exception) {
            throw DocumentationValidationException::fromErrors(
                ["Unable to parse OpenAPI artifact '{$path}': {$exception->getMessage()}"],
                'OpenAPI ingest failed'
            );
        }

        if (! is_array($decoded)) {
            throw DocumentationValidationException::fromErrors(
                ["OpenAPI artifact '{$path}' must decode to an object/map."],
                'OpenAPI ingest failed'
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{
     *   operation_id: string,
     *   http_method: string,
     *   path: string,
     *   summary: string|null,
     *   description: string|null,
     *   section: string|null,
     *   tags: array<int, string>,
     *   linked_doc_slugs: array<int, string>
     * }>
     */
    private function extractOperations(array $spec, string $linkedSlugsExtension): array
    {
        $paths = $spec['paths'] ?? null;

        if (! is_array($paths) || $paths === []) {
            throw DocumentationValidationException::fromErrors(
                ['OpenAPI artifact must contain at least one path operation.'],
                'OpenAPI ingest failed'
            );
        }

        /** @var array<int, string> $allowedMethods */
        $allowedMethods = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];
        $operations = [];
        $seenOperationIds = [];
        $errors = [];

        foreach ($paths as $path => $pathItem) {
            if (! is_string($path) || trim($path) === '') {
                $errors[] = 'Encountered OpenAPI path with a non-string or empty key.';

                continue;
            }

            if (! is_array($pathItem)) {
                $errors[] = "Path '{$path}' must map to an object of HTTP methods.";

                continue;
            }

            foreach ($allowedMethods as $method) {
                if (! array_key_exists($method, $pathItem)) {
                    continue;
                }

                $operation = $pathItem[$method];
                if (! is_array($operation)) {
                    $errors[] = "Path '{$path}' method '{$method}' must be an operation object.";

                    continue;
                }

                $operationId = $this->normalizeNullableString($operation['operationId'] ?? null);
                if ($operationId === null) {
                    $errors[] = "Path '{$path}' method '{$method}' is missing operationId.";

                    continue;
                }

                if (isset($seenOperationIds[$operationId])) {
                    $errors[] = "Duplicate operationId '{$operationId}' found in artifact.";

                    continue;
                }
                $seenOperationIds[$operationId] = true;

                $linkedDocSlugs = $this->extractLinkedDocSlugs($operation, $linkedSlugsExtension);
                if ($linkedDocSlugs === []) {
                    $errors[] = "Operation '{$operationId}' is missing linked narrative slugs via '{$linkedSlugsExtension}'.";

                    continue;
                }

                $tags = $this->normalizeStringList($operation['tags'] ?? []);

                $operations[] = [
                    'operation_id' => $operationId,
                    'http_method' => strtoupper($method),
                    'path' => $path,
                    'summary' => $this->normalizeNullableString($operation['summary'] ?? null),
                    'description' => $this->normalizeNullableString($operation['description'] ?? null),
                    'section' => $tags[0] ?? null,
                    'tags' => $tags,
                    'linked_doc_slugs' => $linkedDocSlugs,
                ];
            }
        }

        if ($operations === [] && $errors === []) {
            $errors[] = 'No ingestible HTTP operations were found in OpenAPI artifact.';
        }

        if ($errors !== []) {
            throw DocumentationValidationException::fromErrors($errors, 'OpenAPI ingest failed');
        }

        usort($operations, fn (array $left, array $right): int => $left['operation_id'] <=> $right['operation_id']);

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<int, string>
     */
    private function extractLinkedDocSlugs(array $operation, string $linkedSlugsExtension): array
    {
        $value = $operation[$linkedSlugsExtension] ?? null;

        if ($value === null && $linkedSlugsExtension !== 'x-linked-doc-slugs') {
            $value = $operation['x-linked-doc-slugs'] ?? null;
        }

        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return $this->normalizeStringList($value);
    }

    /**
     * @param  array<int, array{
     *   operation_id: string,
     *   linked_doc_slugs: array<int, string>
     * }>  $operations
     * @return array<string, int>
     */
    private function resolveLinkedSlugs(array $operations): array
    {
        $slugs = [];
        foreach ($operations as $operation) {
            $slugs = array_merge($slugs, $operation['linked_doc_slugs']);
        }

        $slugs = array_values(array_unique($slugs));
        sort($slugs);

        $slugToEntryId = DocumentationEntry::query()
            ->where('domain', 'api_doc')
            ->whereIn('slug', $slugs)
            ->pluck('id', 'slug')
            ->all();

        $errors = [];
        foreach ($operations as $operation) {
            foreach ($operation['linked_doc_slugs'] as $slug) {
                if (! array_key_exists($slug, $slugToEntryId)) {
                    $errors[] = "Operation '{$operation['operation_id']}' references unresolved linked_doc_slug '{$slug}'.";
                }
            }
        }

        if ($errors !== []) {
            throw DocumentationValidationException::fromErrors($errors, 'OpenAPI ingest failed');
        }

        return $slugToEntryId;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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
}
