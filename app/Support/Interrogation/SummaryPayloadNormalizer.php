<?php

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

        $summary['summary_markdown'] = $embedded['clean_markdown'];

        foreach ($fields as $field) {
            $existing = $this->toStringList($summary[$field] ?? null);

            if ($existing === [] && isset($embedded['lists'][$field])) {
                $existing = $embedded['lists'][$field];
            }

            $summary[$field] = $existing;
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
            $name = strtolower(trim((string) ($matches[1][0] ?? '')));
            $tag = (string) ($matches[0][0] ?? '');
            $tagStart = (int) ($matches[0][1] ?? 0);

            if ($tag === '') {
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
}
