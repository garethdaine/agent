<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

class PlanPayloadGuard
{
    /**
     * @param  array<string, mixed>  $plan
     * @return array{valid:bool,reason:string}
     */
    public function validate(array $plan): array
    {
        $markdown = trim((string) ($plan['plan_markdown'] ?? $plan['plan'] ?? ''));
        $sections = $this->toStringList($plan['sections'] ?? null);
        $risks = $this->toStringList($plan['risks'] ?? null);
        $assumptions = $this->toStringList($plan['assumptions'] ?? null);

        if ($markdown === '') {
            return ['valid' => false, 'reason' => 'plan_markdown is empty'];
        }

        if (preg_match('/^plan not generated yet\.?$/i', $markdown) === 1) {
            return ['valid' => false, 'reason' => 'plan_markdown is placeholder text'];
        }

        if ($this->looksLikeOperationalStatusText($markdown)) {
            return ['valid' => false, 'reason' => 'operational status text was returned instead of a plan'];
        }

        $headingCount = preg_match_all('/^\s{0,3}#{1,6}\s+\S/m', $markdown) ?: 0;
        $bulletCount = preg_match_all('/^\s*(?:[-*+]|\d+\.)\s+\S/m', $markdown) ?: 0;
        $listItemCount = count($sections) + count($risks) + count($assumptions);

        if ($listItemCount === 0 && $headingCount === 0 && $bulletCount < 3) {
            return ['valid' => false, 'reason' => 'plan has no structured sections, lists, or bullets'];
        }

        $minChars = max(120, (int) config('agent.interrogation.plan_guard_min_markdown_chars', 220));
        if (mb_strlen($markdown) < $minChars && $listItemCount === 0) {
            return ['valid' => false, 'reason' => 'plan_markdown is too short and lacks supporting structured lists'];
        }

        return ['valid' => true, 'reason' => 'ok'];
    }

    private function looksLikeOperationalStatusText(string $markdown): bool
    {
        $text = trim($markdown);
        $lower = mb_strtolower($text);

        if (str_contains($lower, 'reviewing the locked discovery baseline')) {
            return true;
        }

        if (preg_match('/\b(i(?:\s+am|\'m)?\s+revising\s+the\s+plan|then\s+i(?:\s+will|\'ll)\s+rewrite)\b/i', $text) === 1) {
            return true;
        }

        $operationalVerb = preg_match('/\b(reviewing|analyz(?:e|ing)|inspecting|extracting|locating|gathering|checking)\b/i', $text) === 1;
        $intentionVerb = preg_match('/\b(rewrite|generate|produce|returning|return)\b/i', $text) === 1;
        $contextWord = preg_match('/\b(summary|baseline|docs|schema|contracts|mappings)\b/i', $text) === 1;
        $isStructured = preg_match('/^\s{0,3}#{1,6}\s+\S/m', $text) === 1
            || preg_match('/^\s*(?:[-*+]|\d+\.)\s+\S/m', $text) === 1;

        return $operationalVerb && $intentionVerb && $contextWord && ! $isStructured;
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
