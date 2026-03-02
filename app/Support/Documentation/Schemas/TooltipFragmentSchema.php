<?php

declare(strict_types=1);

namespace App\Support\Documentation\Schemas;

class TooltipFragmentSchema
{
    /**
     * @param  array<string, mixed>  $fragment
     * @return array<string, mixed>
     */
    public function validate(array $fragment, string $sourcePath, int $index): array
    {
        $errors = [];

        /** @var array<int, string> $requiredFields */
        $requiredFields = config('documentation.tooltips.required_fields', []);
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $fragment)) {
                $errors[] = "Tooltip fragment #{$index} is missing required field '{$field}'.";
            }
        }

        $uiKey = $fragment['ui_key'] ?? null;
        if (! is_string($uiKey) || trim($uiKey) === '') {
            $errors[] = "Tooltip fragment #{$index} field 'ui_key' must be a non-empty string.";
        } else {
            $uiKeyPattern = (string) config('documentation.tooltips.ui_key_pattern', '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/');
            if (preg_match($uiKeyPattern, $uiKey) !== 1) {
                $errors[] = "Tooltip fragment #{$index} field 'ui_key' is invalid.";
            }
        }

        $shortText = $fragment['short_text'] ?? null;
        if (! is_string($shortText) || trim($shortText) === '') {
            $errors[] = "Tooltip fragment #{$index} field 'short_text' must be a non-empty string.";
        } else {
            $shortTextMax = (int) config('documentation.tooltips.short_text_max', 120);
            if (mb_strlen($shortText) > $shortTextMax) {
                $errors[] = "Tooltip fragment #{$index} field 'short_text' must be {$shortTextMax} chars or fewer.";
            }
        }

        /** @var array<int, string> $allowedSeverity */
        $allowedSeverity = config('documentation.allowed_severity', []);
        $severity = $fragment['severity'] ?? null;
        if (! is_string($severity) || ! in_array($severity, $allowedSeverity, true)) {
            $errors[] = 'Tooltip fragment #'.$index." field 'severity' contains an unknown value.";
        }

        $links = $fragment['links'] ?? null;
        if (! is_array($links)) {
            $errors[] = "Tooltip fragment #{$index} field 'links' must be an array.";
        } else {
            foreach ($links as $linkIndex => $link) {
                if (! is_array($link)) {
                    $errors[] = "Tooltip fragment #{$index} links entry {$linkIndex} must be an object.";

                    continue;
                }

                $label = $link['label'] ?? null;
                $url = $link['url'] ?? null;
                if (! is_string($label) || trim($label) === '') {
                    $errors[] = "Tooltip fragment #{$index} links entry {$linkIndex} requires a non-empty 'label'.";
                }

                if (! is_string($url) || trim($url) === '') {
                    $errors[] = "Tooltip fragment #{$index} links entry {$linkIndex} requires a non-empty 'url'.";

                    continue;
                }

                if (! $this->isAllowedLink($url)) {
                    $errors[] = "Tooltip fragment #{$index} links entry {$linkIndex} URL must use allowed domains.";
                }
            }
        }

        $metadata = $fragment['metadata'] ?? null;
        if (! is_array($metadata)) {
            $errors[] = "Tooltip fragment #{$index} field 'metadata' must be an object.";
        } else {
            /** @var array<int, string> $requiredMetadata */
            $requiredMetadata = config('documentation.tooltips.required_metadata', []);
            foreach ($requiredMetadata as $metadataField) {
                if (! array_key_exists($metadataField, $metadata)) {
                    $errors[] = "Tooltip fragment #{$index} metadata is missing '{$metadataField}'.";
                }
            }

            if (array_key_exists('owner', $metadata) && (! is_string($metadata['owner']) || trim($metadata['owner']) === '')) {
                $errors[] = "Tooltip fragment #{$index} metadata 'owner' must be a non-empty string.";
            }

            /** @var array<int, string> $allowedLocales */
            $allowedLocales = config('documentation.locale.allowed', ['en']);
            if (array_key_exists('locale', $metadata) && (! is_string($metadata['locale']) || ! in_array($metadata['locale'], $allowedLocales, true))) {
                $errors[] = "Tooltip fragment #{$index} metadata 'locale' must be one of: ".implode(', ', $allowedLocales).'.';
            }
        }

        if ($errors !== []) {
            throw DocumentationValidationException::fromErrors(
                $errors,
                "Tooltip schema validation failed for {$sourcePath}"
            );
        }

        return $fragment;
    }

    private function isAllowedLink(string $url): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return false;
        }

        $allowRelative = (bool) config('documentation.tooltips.allow_relative_links', true);
        if ($allowRelative && str_starts_with($trimmed, '/')) {
            return true;
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        /** @var array<int, string> $allowedDomains */
        $allowedDomains = config('documentation.allowed_domains', []);
        foreach ($allowedDomains as $domain) {
            $domain = strtolower(trim($domain));
            if ($domain === '') {
                continue;
            }

            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
