<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

class VersionedSchemaRegistry
{
    /**
     * @return array{schema_hash: string, normalizer_version: string, registry_revision: string}
     */
    public function resolve(string $schemaName, string $schemaVersion): array
    {
        $schemaName = trim($schemaName);
        $schemaVersion = trim($schemaVersion);

        $registryRevision = (string) config('agent.telemetry.schema_registry.registry_revision', 'registry-unpinned');
        $schemas = config('agent.telemetry.schema_registry.schemas', []);

        $schemaConfig = null;
        if (is_array($schemas)) {
            $candidate = data_get($schemas, $schemaName.'.'.$schemaVersion);
            if (is_array($candidate)) {
                $schemaConfig = $candidate;
            }
        }

        $schemaHash = is_array($schemaConfig) && isset($schemaConfig['schema_hash'])
            ? (string) $schemaConfig['schema_hash']
            : hash('sha256', $schemaName.'|'.$schemaVersion);

        $normalizerVersion = is_array($schemaConfig) && isset($schemaConfig['normalizer_version'])
            ? (string) $schemaConfig['normalizer_version']
            : 'telemetry-normalizer.v1';

        return [
            'schema_hash' => $schemaHash,
            'normalizer_version' => $normalizerVersion,
            'registry_revision' => $registryRevision,
        ];
    }
}
