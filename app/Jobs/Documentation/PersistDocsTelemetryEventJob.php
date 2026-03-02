<?php

declare(strict_types=1);

namespace App\Jobs\Documentation;

use App\Support\Agent\AuditLogger;
use App\Support\Documentation\DocsTelemetryStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PersistDocsTelemetryEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $eventName,
        public readonly array $payload,
    ) {}

    public function handle(DocsTelemetryStore $store, AuditLogger $auditLogger): void
    {
        $event = [
            ...$this->payload,
            'event_name' => $this->eventName,
            'persisted_at' => now('UTC')->toIso8601String(),
        ];

        $maxRecentFailures = max(1, (int) config('documentation.telemetry.recent_failures_max', 50));

        try {
            $store->appendRecentFailure($event, $maxRecentFailures);
        } catch (Throwable $throwable) {
            Log::warning('documentation.telemetry.store_unavailable', [
                'event_name' => $this->eventName,
                'error' => $throwable->getMessage(),
            ]);
        }

        try {
            Log::channel('docs')->warning('documentation.telemetry', $event);
        } catch (Throwable) {
            Log::warning('documentation.telemetry', $event);
        }

        try {
            $auditLogger->recordSystemAction(
                action: 'docs.telemetry.'.$this->eventName,
                targetType: 'documentation_telemetry',
                targetId: (string) ($event['target_id'] ?? $this->eventName),
                changedFields: array_keys($event),
                before: null,
                after: $event,
                outcome: 'success',
            );
        } catch (Throwable $throwable) {
            Log::warning('documentation.telemetry.audit_unavailable', [
                'event_name' => $this->eventName,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $throwable): void
    {
        Log::warning('documentation.telemetry.queue_failed', [
            'event_name' => $this->eventName,
            'error' => $throwable->getMessage(),
        ]);
    }
}
