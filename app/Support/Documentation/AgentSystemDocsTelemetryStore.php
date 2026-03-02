<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Models\AgentSystemState;
use Illuminate\Support\Facades\DB;
use JsonException;

class AgentSystemDocsTelemetryStore implements DocsTelemetryStore
{
    private const COUNTER_PREFIX = 'docs_telemetry:counter:';

    private const RECENT_FAILURES_KEY = 'docs_telemetry:recent_failures';

    public function incrementCounter(string $counter, int $incrementBy = 1): void
    {
        $counter = trim($counter);
        if ($counter === '' || $incrementBy === 0) {
            return;
        }

        DB::transaction(function () use ($counter, $incrementBy): void {
            $key = self::COUNTER_PREFIX.$counter;
            $state = AgentSystemState::query()->whereKey($key)->lockForUpdate()->first();
            $currentValue = $state !== null ? $this->parseCounterValue($state->value) : 0;
            $nextValue = max(0, $currentValue + $incrementBy);

            AgentSystemState::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) $nextValue,
                    'updated_at' => now('UTC'),
                ]
            );
        });
    }

    /**
     * @param  array<int, string>  $counterNames
     * @return array<string, int>
     */
    public function getCounters(array $counterNames): array
    {
        $normalizedNames = array_values(array_unique(array_filter(array_map(
            static fn ($counter): string => is_string($counter) ? trim($counter) : '',
            $counterNames
        ))));

        if ($normalizedNames === []) {
            return [];
        }

        $keys = array_map(static fn (string $counter): string => self::COUNTER_PREFIX.$counter, $normalizedNames);

        $states = AgentSystemState::query()
            ->whereIn('key', $keys)
            ->get(['key', 'value']);

        $byKey = [];
        foreach ($states as $state) {
            $byKey[(string) $state->key] = $this->parseCounterValue($state->value);
        }

        $counters = [];
        foreach ($normalizedNames as $counter) {
            $storageKey = self::COUNTER_PREFIX.$counter;
            $counters[$counter] = $byKey[$storageKey] ?? 0;
        }

        return $counters;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function appendRecentFailure(array $event, int $maxItems = 50): void
    {
        $maxItems = max(1, min(500, $maxItems));

        DB::transaction(function () use ($event, $maxItems): void {
            $state = AgentSystemState::query()->whereKey(self::RECENT_FAILURES_KEY)->lockForUpdate()->first();
            $events = $state !== null ? $this->parseRecentFailures($state->value) : [];

            $events[] = $event;
            if (count($events) > $maxItems) {
                $events = array_slice($events, -1 * $maxItems);
            }

            AgentSystemState::query()->updateOrCreate(
                ['key' => self::RECENT_FAILURES_KEY],
                [
                    'value' => $this->encodeRecentFailures($events),
                    'updated_at' => now('UTC'),
                ]
            );
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentFailures(int $limit = 20): array
    {
        $safeLimit = max(1, min($limit, 100));

        $state = AgentSystemState::query()->find(self::RECENT_FAILURES_KEY);
        if ($state === null) {
            return [];
        }

        $events = $this->parseRecentFailures($state->value);
        if ($events === []) {
            return [];
        }

        $events = array_reverse($events);

        return array_slice($events, 0, $safeLimit);
    }

    private function parseCounterValue(mixed $value): int
    {
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (! is_string($value)) {
            return 0;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return 0;
        }

        return max(0, (int) $trimmed);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseRecentFailures(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $events = [];

        foreach ($decoded as $row) {
            if (is_array($row)) {
                $events[] = $row;
            }
        }

        return $events;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function encodeRecentFailures(array $events): string
    {
        try {
            $encoded = json_encode($events, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? $encoded : '[]';
        } catch (JsonException) {
            return '[]';
        }
    }
}
