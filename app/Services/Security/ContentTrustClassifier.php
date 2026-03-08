<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\Security\ContentTrustLevel;

class ContentTrustClassifier
{
    private const array PREFIX_MAP = [
        'system.' => ContentTrustLevel::Trusted,
        'user.' => ContentTrustLevel::Trusted,
        'session.' => ContentTrustLevel::Contextual,
    ];

    public function __construct(
        private readonly SecurityConfigProvider $config, // @phpstan-ignore property.onlyWritten
    ) {}

    public function classify(string $sourceIdentifier): ContentTrustLevel
    {
        if ($sourceIdentifier === '') {
            return ContentTrustLevel::Untrusted;
        }

        foreach (self::PREFIX_MAP as $prefix => $level) {
            if (str_starts_with($sourceIdentifier, $prefix)) {
                return $level;
            }
        }

        return ContentTrustLevel::Untrusted;
    }

    public function classifyToolResult(string $toolName, array $args): ContentTrustLevel
    {
        $qualifiedName = isset($args['operation'])
            ? $toolName.'.'.$args['operation']
            : $toolName;

        return $this->classify($qualifiedName);
    }

    public function isUntrusted(ContentTrustLevel $level): bool
    {
        return $level === ContentTrustLevel::Untrusted;
    }
}
