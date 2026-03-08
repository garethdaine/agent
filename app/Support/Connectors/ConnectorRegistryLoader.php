<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\AgentConnector;
use Illuminate\Support\Facades\Log;

class ConnectorRegistryLoader
{
    public function __construct(
        private readonly ConnectorManifestValidator $validator,
    ) {}

    public function sync(?string $manifestsPath = null): RegistrySyncResult
    {
        $path = $manifestsPath ?? config('connectors.manifests_path');
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $deprecated = 0;
        $errors = 0;

        $manifestNames = [];

        if (! is_dir($path)) {
            return new RegistrySyncResult($created, $updated, $skipped, $deprecated, $errors);
        }

        $directories = glob($path.'/*', GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            $manifestFile = $directory.'/connector.json';

            if (! file_exists($manifestFile)) {
                continue;
            }

            $json = file_get_contents($manifestFile);
            $manifest = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Invalid JSON in connector manifest', [
                    'path' => $manifestFile,
                    'error' => json_last_error_msg(),
                ]);
                $errors++;

                continue;
            }

            $result = $this->validator->validate($manifest);

            if (! $result->valid) {
                Log::warning('Invalid connector manifest', [
                    'path' => $manifestFile,
                    'errors' => $result->errors,
                ]);
                $errors++;

                continue;
            }

            $name = $manifest['name'];
            $manifestNames[] = $name;

            $existing = AgentConnector::where('name', $name)->first();
            $attributes = $this->manifestToAttributes($manifest);

            if ($existing) {
                if ($this->isUnchanged($existing, $attributes)) {
                    $skipped++;

                    continue;
                }

                $existing->update($attributes);
                $updated++;
            } else {
                AgentConnector::create($attributes);
                $created++;
            }
        }

        // Mark connectors that no longer have manifests as deprecated
        if (! empty($manifestNames)) {
            $deprecatedCount = AgentConnector::whereNotIn('name', $manifestNames)
                ->where('status', '!=', AgentConnector::STATUS_DEPRECATED)
                ->update(['status' => AgentConnector::STATUS_DEPRECATED]);
            $deprecated = $deprecatedCount;
        }

        return new RegistrySyncResult($created, $updated, $skipped, $deprecated, $errors);
    }

    private function isUnchanged(AgentConnector $existing, array $attributes): bool
    {
        $compareKeys = ['version', 'display_name', 'description', 'auth_type', 'base_url', 'status'];

        foreach ($compareKeys as $key) {
            if (($attributes[$key] ?? null) !== $existing->getAttribute($key)) {
                return false;
            }
        }

        $arrayKeys = ['auth_config', 'rate_limits', 'actions', 'webhooks', 'industries'];

        foreach ($arrayKeys as $key) {
            $a = $attributes[$key] ?? [];
            $b = $existing->getAttribute($key) ?? [];

            if ($this->normalizeForComparison($a) !== $this->normalizeForComparison($b)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeForComparison(mixed $value): string
    {
        if (is_array($value)) {
            // Recursively sort associative arrays by key for consistent comparison
            array_walk_recursive($value, function (&$item) {
                // no-op, just ensure traversal
            });

            $value = $this->recursiveKeySort($value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function recursiveKeySort(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = $this->recursiveKeySort($value);
            }
        }

        if ($this->isAssociative($array)) {
            ksort($array);
        }

        return $array;
    }

    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function manifestToAttributes(array $manifest): array
    {
        return [
            'name' => $manifest['name'],
            'display_name' => $manifest['display_name'],
            'description' => $manifest['description'],
            'category' => $manifest['category'] ?? 'general',
            'industries' => $manifest['industries'] ?? [],
            'version' => $manifest['version'],
            'auth_type' => $manifest['auth_type'],
            'auth_config' => $manifest['auth_config'],
            'base_url' => $manifest['base_url'],
            'rate_limits' => $manifest['rate_limits'],
            'actions' => $manifest['actions'],
            'webhooks' => $manifest['webhooks'] ?? [],
            'mcp_tool_prefix' => $manifest['mcp_tool_prefix'] ?? null,
            'status' => $manifest['status'] ?? AgentConnector::STATUS_AVAILABLE,
        ];
    }
}
