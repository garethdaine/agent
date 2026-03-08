<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

class SummaryPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function normalize(array $summary): array
    {
        $fields = ['goals', 'constraints', 'acceptance_criteria', 'open_questions'];

        $markdown = (string) ($summary['summary_markdown'] ?? $summary['summary'] ?? '');
        $embedded = $this->extractEmbeddedParameterLists($markdown, $fields);

        $summary['summary_markdown'] = $this->sanitizeSummaryMarkdown($embedded['clean_markdown']);

        foreach ($fields as $field) {
            $existing = $this->toStringList($summary[$field] ?? null);

            if ($existing === [] && isset($embedded['lists'][$field])) {
                $existing = $embedded['lists'][$field];
            }

            $summary[$field] = $this->sanitizeList($existing, $field);
        }

        $summary['private_notes'] = (string) ($summary['private_notes'] ?? '');

        return $summary;
    }

    /**
     * @param  array<int, string>  $fields
     * @return array{clean_markdown:string,lists:array<string,array<int,string>>}
     */
    private function extractEmbeddedParameterLists(string $markdown, array $fields): array
    {
        if ($markdown === '') {
            return ['clean_markdown' => '', 'lists' => []];
        }

        $allowed = array_fill_keys(array_map('strtolower', $fields), true);
        $lists = [];
        $clean = $markdown;
        $offset = 0;
        $tagPattern = '/<parameter\s+name\s*=\s*["\']([^"\']+)["\']\s*>/i';

        while (preg_match($tagPattern, $clean, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $name = strtolower(trim((string) ($matches[1][0] ?? ''))); // @phpstan-ignore nullCoalesce.offset
            $tag = (string) ($matches[0][0] ?? ''); // @phpstan-ignore nullCoalesce.offset
            $tagStart = (int) ($matches[0][1] ?? 0); // @phpstan-ignore nullCoalesce.offset

            if ($tag === '') { // @phpstan-ignore identical.alwaysFalse
                break;
            }

            $afterTag = $tagStart + strlen($tag);

            if (! isset($allowed[$name])) {
                $offset = $afterTag;

                continue;
            }

            [$jsonArray, $jsonEnd] = $this->extractJsonArray($clean, $afterTag);
            $removeEnd = $jsonEnd;

            if ($jsonArray !== null) {
                $decoded = json_decode($jsonArray, true);
                if (is_array($decoded)) {
                    $lists[$name] = $this->toStringList($decoded);
                }
            }

            $closing = substr($clean, $removeEnd);
            if (preg_match('/^\s*<\/parameter>/i', $closing, $closingMatch) === 1) {
                $removeEnd += strlen((string) $closingMatch[0]);
            }

            $clean = substr($clean, 0, $tagStart).substr($clean, $removeEnd);
            $offset = $tagStart;
        }

        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B,");

        return [
            'clean_markdown' => $clean,
            'lists' => $lists,
        ];
    }

    /**
     * @return array{0:?string,1:int}
     */
    private function extractJsonArray(string $text, int $offset): array
    {
        $length = strlen($text);
        $index = $offset;

        while ($index < $length && ctype_space($text[$index])) {
            $index++;
        }

        if ($index >= $length || $text[$index] !== '[') {
            return [null, $offset];
        }

        $start = $index;
        $depth = 0;
        $inString = false;
        $quote = '';
        $escaped = false;

        while ($index < $length) {
            $char = $text[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $inString = false;
                }

                $index++;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $quote = $char;
                $index++;

                continue;
            }

            if ($char === '[') {
                $depth++;
                $index++;

                continue;
            }

            if ($char === ']') {
                $depth--;
                $index++;

                if ($depth === 0) {
                    return [substr($text, $start, $index - $start), $index];
                }

                continue;
            }

            $index++;
        }

        return [null, $offset];
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

    private function sanitizeSummaryMarkdown(string $markdown): string
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
     * @param  array<int, string>  $items
     * @return array<int, string>
     */
    private function sanitizeList(array $items, string $field): array
    {
        $guard = $field === 'open_questions' ? new QuestionPayloadGuard : null;

        return array_values(array_filter(
            $items,
            function (string $item) use ($field, $guard): bool {
                if ($this->isEstimateOrTimelineContent($item)) {
                    return false;
                }

                if ($field !== 'open_questions') {
                    return true;
                }

                $validation = $guard?->validate([
                    'question_text' => $item,
                    'answer_type' => 'freetext',
                    'options' => [],
                    'progress_estimate' => 0,
                    'is_complete' => false,
                ]);

                return (bool) ($validation['valid'] ?? false);
            },
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

        if (preg_match('/\b(total\s+estimated\s+effort|estimated\s+effort|effort\s+estimate|estimate\s*:|timeline|time[- ]line|critical\s+path|paralleli[sz]able|eta|delivery\s+date|target\s+date)\b/i', $text) === 1) {
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
}
