<?php

declare(strict_types=1);

namespace App\Support\Agent;

use Illuminate\Support\Str;

class WorkflowKey
{
    public const REGEX = '^[a-z0-9._-]+[.]v[1-9][0-9]*$';

    public const ROUTE_PATTERN = '[a-z0-9]+(?:[._-][a-z0-9]+)*\\.v[1-9][0-9]*';

    public static function regex(): string
    {
        $configured = config('agent.workflow_key.regex');

        if (! is_string($configured) || trim($configured) === '') {
            return self::REGEX;
        }

        return trim($configured);
    }

    public static function routePattern(): string
    {
        $configured = config('agent.workflow_key.route_pattern');

        if (! is_string($configured) || trim($configured) === '') {
            return self::ROUTE_PATTERN;
        }

        return trim($configured);
    }

    public static function isValid(?string $workflowKey): bool
    {
        if (! is_string($workflowKey)) {
            return false;
        }

        $normalized = trim($workflowKey);
        if ($normalized === '') {
            return false;
        }

        if (preg_match('/'.self::regex().'/', $normalized) !== 1) {
            return false;
        }

        $base = preg_replace('/[.]v[1-9][0-9]*$/', '', $normalized);
        if (! is_string($base) || $base === '') {
            return false;
        }

        if (preg_match('/^[._-]|[._-]$|[._-]{2,}/', $base) === 1) {
            return false;
        }

        return true;
    }

    public static function normalize(?string $workflowKey): ?string
    {
        if (! is_string($workflowKey)) {
            return null;
        }

        $normalized = trim($workflowKey);

        return $normalized !== '' ? $normalized : null;
    }

    public static function deriveFromName(string $name, int|string|null $suffix = null): string
    {
        $base = self::sanitizeBase($name);

        if ($base === '') {
            $base = 'workflow';
        }

        $normalizedSuffix = self::sanitizeSuffix($suffix);
        if ($normalizedSuffix !== null) {
            $base .= '-'.$normalizedSuffix;
        }

        $candidate = $base.'.v1';
        if (self::isValid($candidate)) {
            return $candidate;
        }

        return 'workflow.v1';
    }

    public static function resolve(?string $providedWorkflowKey, string $name, int|string|null $suffix = null): string
    {
        $normalized = self::normalize($providedWorkflowKey);
        if ($normalized !== null && self::isValid($normalized)) {
            return $normalized;
        }

        return self::deriveFromName($name, $suffix);
    }

    private static function sanitizeBase(string $name): string
    {
        return (string) Str::of(Str::lower($name))
            ->replaceMatches('/[^a-z0-9._-]+/', '-')
            ->replaceMatches('/[-_.]{2,}/', '-')
            ->trim('-._');
    }

    private static function sanitizeSuffix(int|string|null $suffix): ?string
    {
        if ($suffix === null) {
            return null;
        }

        $normalized = trim((string) $suffix);
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized);
        $normalized = trim((string) $normalized, '-');

        return $normalized !== '' ? Str::lower($normalized) : null;
    }
}
