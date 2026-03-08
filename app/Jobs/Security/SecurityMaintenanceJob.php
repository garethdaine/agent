<?php

declare(strict_types=1);

namespace App\Jobs\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\Runtime\RuntimeSession;
use App\Models\Security\SecurityEvent;
use App\Services\Security\SecurityConfigProvider;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SecurityMaintenanceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('memory-formation');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(SecurityConfigProvider $config): void
    {
        $retentionDays = (int) $config->get('file_provenance_retention_days');
        $batchSize = (int) $config->get('security_purge_batch_size');
        $cutoff = Carbon::now()->subDays($retentionDays);

        $eventsPurged = $this->purgeSecurityEvents($cutoff, $batchSize);
        $provenancePurged = $this->purgeFileProvenance($cutoff);

        $this->logPurgeCounts($eventsPurged, $provenancePurged);

        Log::info('SecurityMaintenanceJob: Maintenance completed', [
            'events_purged' => $eventsPurged,
            'provenance_sessions_cleaned' => $provenancePurged,
            'retention_days' => $retentionDays,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('SecurityMaintenanceJob: Job failed permanently', [
            'error' => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['security', 'maintenance'];
    }

    private function purgeSecurityEvents(Carbon $cutoff, int $batchSize): int
    {
        $totalPurged = 0;

        do {
            $deleted = SecurityEvent::where('created_at', '<', $cutoff)
                ->where('event_type', '!=', SecurityEventType::RulePurged)
                ->limit($batchSize)
                ->delete();

            $totalPurged += $deleted;
        } while ($deleted >= $batchSize);

        return $totalPurged;
    }

    private function purgeFileProvenance(Carbon $cutoff): int
    {
        $sessionsCleaned = 0;

        RuntimeSession::whereNotNull('file_provenance')
            ->chunkById(100, function ($sessions) use ($cutoff, &$sessionsCleaned) {
                foreach ($sessions as $session) {
                    $provenance = $session->file_provenance;

                    if (! is_array($provenance) || empty($provenance)) {
                        continue;
                    }

                    $filtered = array_values(array_filter($provenance, function (array $entry) use ($cutoff) {
                        $createdAt = Carbon::parse($entry['createdAt'] ?? now());

                        return $createdAt->isAfter($cutoff);
                    }));

                    if (count($filtered) !== count($provenance)) {
                        $session->update([
                            'file_provenance' => empty($filtered) ? null : $filtered,
                        ]);
                        $sessionsCleaned++;
                    }
                }
            });

        return $sessionsCleaned;
    }

    private function logPurgeCounts(int $eventsPurged, int $provenancePurged): void
    {
        SecurityEvent::create([
            'event_type' => SecurityEventType::RulePurged,
            'severity' => 'low',
            'details_json' => [
                'events_purged' => $eventsPurged,
                'provenance_sessions_cleaned' => $provenancePurged,
                'timestamp' => now()->toIso8601String(),
            ],
            'created_at' => now(),
        ]);
    }
}
