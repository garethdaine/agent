<?php

namespace App\Support\Interrogation;

class PlanPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function normalize(array $plan): array
    {
        $plan['plan_markdown'] = $this->sanitizePlanMarkdown((string) ($plan['plan_markdown'] ?? $plan['plan'] ?? ''));
        $plan['sections'] = $this->sanitizeList($plan['sections'] ?? null);
        $plan['risks'] = $this->sanitizeList($plan['risks'] ?? null);
        $plan['assumptions'] = $this->sanitizeList($plan['assumptions'] ?? null);

        return $plan;
    }

    private function sanitizePlanMarkdown(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        $lines = preg_split('/\R/', $markdown) ?: [];
        $filtered = [];
        $skipSection = false;

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);

            if ($this->isHeadingLine($trimmed)) {
                $skipSection = $this->isEstimateOrTimelineContent($this->headingText($trimmed));
                if ($skipSection) {
                    continue;
                }
            }

            if ($skipSection) {
                continue;
            }

            if ($trimmed !== '' && $this->isEstimateOrTimelineContent($trimmed)) {
                continue;
            }

            $filtered[] = (string) $line;
        }

        $clean = implode("\n", $filtered);
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;

        return trim($clean);
    }

    /**
     * @return array<int, string>
     */
    private function sanitizeList(mixed $value): array
    {
        $items = $this->toStringList($value);

        return array_values(array_filter(
            $items,
            fn (string $item): bool => ! $this->isEstimateOrTimelineContent($item),
        ));
    }

    private function isHeadingLine(string $line): bool
    {
        return (bool) preg_match('/^\s{0,3}#{1,6}\s+/', $line);
    }

    private function headingText(string $line): string
    {
        return trim((string) preg_replace('/^\s{0,3}#{1,6}\s+/', '', $line));
    }

    private function isEstimateOrTimelineContent(string $value): bool
    {
        $text = trim($value);
        if ($text === '') {
            return false;
        }

        if (preg_match('/\b(total\s+estimated\s+effort|estimated\s+effort|effort\s+estimate|timeline|time[- ]line|critical\s+path|paralleli[sz]able|eta|delivery\s+date|target\s+date)\b/i', $text) === 1) {
            return true;
        }

        $hasDuration = preg_match('/\b\d+\s*(?:-|–|to)?\s*\d*\s*(working\s*)?(hours?|days?|weeks?|months?)\b/i', $text) === 1;
        $hasEffortContext = preg_match('/\b(developer|engineer|person|team|staffed?)\b/i', $text) === 1;
        $hasDeliveryVerb = preg_match('/\b(deliver(?:y)?|complete|finish|ship|rollout|release|launch|target)\b/i', $text) === 1;
        $hasBoundedDuration = preg_match('/\b(within|in|by)\s+\d+\s*(hours?|days?|weeks?|months?)\b/i', $text) === 1;

        if ($hasDeliveryVerb && $hasBoundedDuration) {
            return true;
        }

        return $hasDuration && $hasEffortContext;
    }

    /**
     * @return array<int, string>
     */
    private function toStringList(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $normalized = trim($item);
            if ($normalized === '') {
                continue;
            }

            $items[] = $normalized;
        }

        return array_values(array_unique($items));
    }
}
