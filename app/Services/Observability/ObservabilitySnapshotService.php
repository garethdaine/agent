<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Support\Facades\DB;

class ObservabilitySnapshotService
{
    public function snapshot(): array
    {
        $ingestLagSeconds = $this->estimateIngestLag();
        $backlogCount = $this->estimateProjectionBacklog();

        return [
            'ingest_lag_seconds' => $ingestLagSeconds,
            'projection_backlog_count' => $backlogCount,
            'warnings' => $this->collectWarnings($ingestLagSeconds, $backlogCount),
        ];
    }

    private function estimateIngestLag(): ?int
    {
        $table = 'agent_run_events';
        if (! $this->tableExists($table)) {
            return null;
        }

        $latest = DB::table($table)->orderByDesc('id')->value('created_at');

        return $latest ? (int) (now()->timestamp - strtotime($latest)) : null;
    }

    private function estimateProjectionBacklog(): int
    {
        return 0;
    }

    private function collectWarnings(?int $ingestLagSeconds, int $backlogCount): array
    {
        $warnings = [];
        $lagThreshold = config('observability.ingest_lag_warning_seconds', 300);
        $backlogThreshold = config('observability.projection_backlog_warning', 1000);

        if ($ingestLagSeconds !== null && $ingestLagSeconds > $lagThreshold) {
            $warnings[] = 'ingest_lag';
        }
        if ($backlogCount > $backlogThreshold) {
            $warnings[] = 'projection_backlog';
        }

        return $warnings;
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
}
