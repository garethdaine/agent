<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Events\Documentation\DocsSearchUnavailableDetected;
use App\Events\Documentation\DocsSyncOutcomeRecorded;
use App\Events\Documentation\TooltipKeyMissingDetected;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocsTelemetryService
{
    public const COUNTER_TOOLTIP_MISSING_KEY_TOTAL = 'tooltip_missing_key_total';

    public const COUNTER_DOCS_SEARCH_UNAVAILABLE_TOTAL = 'docs_search_unavailable_total';

    public const COUNTER_DOCS_SYNC_SUCCESS_TOTAL = 'docs_sync_success_total';

    public const COUNTER_DOCS_SYNC_FAILURE_TOTAL = 'docs_sync_failure_total';

    /** @var array<int, string> */
    private const COUNTERS = [
        self::COUNTER_TOOLTIP_MISSING_KEY_TOTAL,
        self::COUNTER_DOCS_SEARCH_UNAVAILABLE_TOTAL,
        self::COUNTER_DOCS_SYNC_SUCCESS_TOTAL,
        self::COUNTER_DOCS_SYNC_FAILURE_TOTAL,
    ];

    public function __construct(
        private readonly Dispatcher $events,
        private readonly DocsTelemetryStore $store,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordTooltipMiss(string $uiKey, string $reason, array $context = []): void
    {
        $normalizedUiKey = trim($uiKey);
        $normalizedReason = trim($reason) !== '' ? trim($reason) : 'unknown';
        $occurredAt = now('UTC')->toIso8601String();

        $this->safeIncrementCounter(self::COUNTER_TOOLTIP_MISSING_KEY_TOTAL);

        if ($this->shouldSuppressTooltipMissingKeyEvent($normalizedUiKey, $normalizedReason, $context)) {
            return;
        }

        $this->events->dispatch(new TooltipKeyMissingDetected(
            uiKey: $normalizedUiKey,
            reason: $normalizedReason,
            context: $context,
            occurredAt: $occurredAt,
        ));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordSearchUnavailable(
        string $query,
        ?string $routeName,
        Throwable $throwable,
        array $context = []
    ): void {
        $this->safeIncrementCounter(self::COUNTER_DOCS_SEARCH_UNAVAILABLE_TOTAL);

        $normalizedQuery = trim($query);
        $queryHash = hash('sha256', $normalizedQuery);

        $this->events->dispatch(new DocsSearchUnavailableDetected(
            routeName: $this->normalizeNullableString($routeName),
            queryHash: $queryHash,
            errorClass: $throwable::class,
            errorMessage: trim($throwable->getMessage()),
            context: $context,
            occurredAt: now('UTC')->toIso8601String(),
        ));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function recordSyncOutcome(
        string $mode,
        string $source,
        bool $success,
        array $summary = [],
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): void {
        $this->safeIncrementCounter(
            $success ? self::COUNTER_DOCS_SYNC_SUCCESS_TOTAL : self::COUNTER_DOCS_SYNC_FAILURE_TOTAL
        );

        $this->events->dispatch(new DocsSyncOutcomeRecorded(
            mode: trim($mode),
            source: trim($source),
            success: $success,
            summary: $summary,
            errorCode: $this->normalizeNullableString($errorCode),
            errorMessage: $this->normalizeNullableString($errorMessage),
            occurredAt: now('UTC')->toIso8601String(),
        ));
    }

    /**
     * @return array{
     *   counters: array<string, int>,
     *   recent_failures: array<int, array<string, mixed>>,
     *   generated_at: string
     * }
     */
    public function snapshot(int $recentFailuresLimit = 20): array
    {
        $safeLimit = max(1, min($recentFailuresLimit, 100));

        try {
            $counters = $this->store->getCounters(self::COUNTERS);
        } catch (Throwable $throwable) {
            Log::warning('documentation.telemetry.snapshot.counter_unavailable', [
                'error' => $throwable->getMessage(),
            ]);
            $counters = array_fill_keys(self::COUNTERS, 0);
        }

        try {
            $recentFailures = $this->store->getRecentFailures($safeLimit);
        } catch (Throwable $throwable) {
            Log::warning('documentation.telemetry.snapshot.recent_failures_unavailable', [
                'error' => $throwable->getMessage(),
            ]);
            $recentFailures = [];
        }

        return [
            'counters' => $counters,
            'recent_failures' => $recentFailures,
            'generated_at' => now('UTC')->toIso8601String(),
        ];
    }

    private function safeIncrementCounter(string $counter): void
    {
        try {
            $this->store->incrementCounter($counter);
        } catch (Throwable $throwable) {
            Log::warning('documentation.telemetry.counter_unavailable', [
                'counter' => $counter,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Prevent repeated missing-key noise from flooding telemetry.
     *
     * @param  array<string, mixed>  $context
     */
    private function shouldSuppressTooltipMissingKeyEvent(string $uiKey, string $reason, array $context): bool
    {
        if ($reason !== 'missing_key') {
            return false;
        }

        $windowSeconds = max(1, (int) config('documentation.telemetry.tooltip_missing_key_dedupe_seconds', 120));
        $routeName = $this->normalizeNullableString(is_string($context['route_name'] ?? null) ? $context['route_name'] : null) ?? '-';
        $source = $this->normalizeNullableString(is_string($context['source'] ?? null) ? $context['source'] : null) ?? '-';
        $normalizedUiKey = $uiKey !== '' ? $uiKey : '(empty)';
        $cacheKey = sprintf(
            'docs_telemetry:tooltip_missing_dedupe:%s:%s:%s',
            hash('sha256', $normalizedUiKey),
            hash('sha256', $routeName),
            hash('sha256', $source),
        );

        try {
            return Cache::add($cacheKey, now('UTC')->toIso8601String(), now()->addSeconds($windowSeconds)) === false;
        } catch (Throwable $throwable) {
            Log::warning('documentation.telemetry.dedupe_unavailable', [
                'ui_key' => $normalizedUiKey,
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
