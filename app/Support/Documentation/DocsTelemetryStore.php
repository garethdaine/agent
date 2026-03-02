<?php

declare(strict_types=1);

namespace App\Support\Documentation;

interface DocsTelemetryStore
{
    public function incrementCounter(string $counter, int $incrementBy = 1): void;

    /**
     * @param  array<int, string>  $counterNames
     * @return array<string, int>
     */
    public function getCounters(array $counterNames): array;

    /**
     * @param  array<string, mixed>  $event
     */
    public function appendRecentFailure(array $event, int $maxItems = 50): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentFailures(int $limit = 20): array;
}
