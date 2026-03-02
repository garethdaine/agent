<?php

declare(strict_types=1);

namespace App\Listeners\Documentation;

use App\Events\Documentation\DocsSearchUnavailableDetected;
use App\Events\Documentation\DocsSyncOutcomeRecorded;
use App\Events\Documentation\TooltipKeyMissingDetected;
use App\Jobs\Documentation\PersistDocsTelemetryEventJob;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentationTelemetrySubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(TooltipKeyMissingDetected::class, [self::class, 'handleTooltipKeyMissingDetected']);
        $events->listen(DocsSearchUnavailableDetected::class, [self::class, 'handleDocsSearchUnavailableDetected']);
        $events->listen(DocsSyncOutcomeRecorded::class, [self::class, 'handleDocsSyncOutcomeRecorded']);
    }

    public function handleTooltipKeyMissingDetected(TooltipKeyMissingDetected $event): void
    {
        if (! $this->shouldDispatch(
            sprintf('tooltip_missing_key|%s|%s|%s', $event->occurredAt, $event->uiKey, $event->reason)
        )) {
            return;
        }

        PersistDocsTelemetryEventJob::dispatch('tooltip_missing_key', [
            'event' => 'documentation.tooltip.miss',
            'ui_key' => $event->uiKey,
            'reason' => $event->reason,
            'context' => $event->context,
            'occurred_at' => $event->occurredAt,
            'target_id' => $event->uiKey !== '' ? $event->uiKey : '(empty)',
        ])->onQueue('agent');
    }

    public function handleDocsSearchUnavailableDetected(DocsSearchUnavailableDetected $event): void
    {
        if (! $this->shouldDispatch(
            sprintf('search_unavailable|%s|%s|%s', $event->occurredAt, $event->queryHash, $event->errorClass)
        )) {
            return;
        }

        PersistDocsTelemetryEventJob::dispatch('search_unavailable', [
            'event' => 'documentation.search.unavailable',
            'route_name' => $event->routeName,
            'query_hash' => $event->queryHash,
            'error_class' => $event->errorClass,
            'error_message' => $event->errorMessage,
            'context' => $event->context,
            'occurred_at' => $event->occurredAt,
            'target_id' => $event->queryHash,
        ])->onQueue('agent');
    }

    public function handleDocsSyncOutcomeRecorded(DocsSyncOutcomeRecorded $event): void
    {
        if (! $this->shouldDispatch(
            sprintf('sync_outcome|%s|%s|%s|%s', $event->occurredAt, $event->mode, $event->source, $event->success ? '1' : '0')
        )) {
            return;
        }

        PersistDocsTelemetryEventJob::dispatch('sync_outcome', [
            'event' => 'documentation.sync.outcome',
            'mode' => $event->mode,
            'source' => $event->source,
            'success' => $event->success,
            'summary' => $event->summary,
            'error_code' => $event->errorCode,
            'error_message' => $event->errorMessage,
            'occurred_at' => $event->occurredAt,
            'target_id' => sprintf('%s:%s', $event->mode, $event->source),
        ])->onQueue('agent');
    }

    private function shouldDispatch(string $fingerprint): bool
    {
        $windowSeconds = max(1, (int) config('documentation.telemetry.listener_dedupe_seconds', 30));
        $cacheKey = 'docs_telemetry:listener_dispatch:'.hash('sha256', $fingerprint);

        try {
            return Cache::add($cacheKey, now('UTC')->toIso8601String(), now()->addSeconds($windowSeconds));
        } catch (Throwable $throwable) {
            Log::warning('documentation.telemetry.listener_dedupe_unavailable', [
                'error' => $throwable->getMessage(),
            ]);

            return true;
        }
    }
}
