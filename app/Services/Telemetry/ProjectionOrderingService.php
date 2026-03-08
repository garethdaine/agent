<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

class ProjectionOrderingService
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    public function orderForProjection(array $events): array
    {
        usort($events, function (array $left, array $right): int {
            $leftSequence = (int) ($left['event_sequence'] ?? 0);
            $rightSequence = (int) ($right['event_sequence'] ?? 0);

            if ($leftSequence !== $rightSequence) {
                return $leftSequence <=> $rightSequence;
            }

            $leftEventAt = $this->normalizeComparableInstant($left['event_at'] ?? null);
            $rightEventAt = $this->normalizeComparableInstant($right['event_at'] ?? null);
            if ($leftEventAt !== $rightEventAt) {
                return $leftEventAt <=> $rightEventAt;
            }

            $leftIngestedAt = $this->normalizeComparableInstant($left['ingested_at'] ?? null);
            $rightIngestedAt = $this->normalizeComparableInstant($right['ingested_at'] ?? null);
            if ($leftIngestedAt !== $rightIngestedAt) {
                return $leftIngestedAt <=> $rightIngestedAt;
            }

            $leftEventId = (string) ($left['event_id'] ?? '');
            $rightEventId = (string) ($right['event_id'] ?? '');
            if ($leftEventId !== $rightEventId) {
                return $leftEventId <=> $rightEventId;
            }

            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        return array_values($events); // @phpstan-ignore arrayValues.list
    }

    private function normalizeComparableInstant(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '9999-12-31T23:59:59.999999+00:00';
        }

        $trimmed = trim($value);

        return str_ends_with($trimmed, 'Z')
            ? str_replace('Z', '+00:00', $trimmed)
            : $trimmed;
    }
}
