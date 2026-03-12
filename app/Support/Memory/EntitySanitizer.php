<?php

declare(strict_types=1);

namespace App\Support\Memory;

use Illuminate\Support\Facades\Log;

/**
 * Sanitizes entities before Neo4j storage to prevent Cypher injection
 * and poisoned graph entities.
 *
 * Finding #12, AGE-2292.
 */
class EntitySanitizer
{
    private const MAX_NAME_LENGTH = 255;

    /**
     * Characters that could be used for Cypher injection.
     * Single/double quotes, backslashes, backticks, curly braces, square brackets.
     */
    private const CYPHER_DANGEROUS_PATTERN = '/[\'"`\\\\{}\[\]]/';

    /**
     * Sanitize and validate a batch of entities.
     *
     * Rejects invalid entities (logs warning, skips), deduplicates by type+name,
     * truncates oversized names, and strips control characters.
     *
     * @param  array<array{type?: string|null, name?: string|null, confidence?: float}>  $entities
     * @return array<array{type: string, name: string, confidence?: float}>
     */
    public function sanitize(array $entities): array
    {
        $allowedTypes = config('memory.entity_types', []);
        $seen = [];
        $sanitized = [];

        foreach ($entities as $entity) {
            $type = $entity['type'] ?? null;
            $name = $entity['name'] ?? null;

            // Reject null/missing type
            if ($type === null || ! is_string($type)) { // @phpstan-ignore function.alreadyNarrowedType
                Log::warning('EntitySanitizer: Rejected entity with null/missing type');

                continue;
            }

            // Reject type not in allowed list
            if (! in_array($type, $allowedTypes, true)) {
                Log::warning('EntitySanitizer: Rejected entity with invalid type', ['type' => $type]);

                continue;
            }

            // Reject null/missing name
            if ($name === null || ! is_string($name)) { // @phpstan-ignore function.alreadyNarrowedType
                Log::warning('EntitySanitizer: Rejected entity with null/missing name');

                continue;
            }

            // Strip control characters (U+0000–U+001F, U+007F, U+0080–U+009F)
            $name = preg_replace('/[\x00-\x1F\x7F\x80-\x9F]/u', '', $name) ?? $name;

            // Trim whitespace and reject empty
            $name = trim($name);
            if ($name === '') {
                Log::warning('EntitySanitizer: Rejected entity with empty name after sanitization');

                continue;
            }

            // Reject names containing Cypher-dangerous characters
            if (preg_match(self::CYPHER_DANGEROUS_PATTERN, $name)) {
                Log::warning('EntitySanitizer: Rejected entity with dangerous characters in name', [
                    'name' => mb_substr($name, 0, 50),
                ]);

                continue;
            }

            // Truncate oversized names
            if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
                $name = mb_substr($name, 0, self::MAX_NAME_LENGTH);
            }

            // Deduplicate by type+name (keep first occurrence)
            $dedupeKey = $type.'::'.$name;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $sanitizedEntity = [
                'type' => $type,
                'name' => $name,
            ];

            // Preserve optional fields
            if (isset($entity['confidence'])) {
                $sanitizedEntity['confidence'] = $entity['confidence'];
            }
            if (isset($entity['classification'])) { // @phpstan-ignore isset.offset
                $sanitizedEntity['classification'] = $entity['classification'];
            }

            $sanitized[] = $sanitizedEntity;
        }

        return $sanitized;
    }
}
