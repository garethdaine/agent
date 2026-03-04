<?php

declare(strict_types=1);

namespace App\Repositories\Projection;

final class WorkflowReliabilityCurrentRepository extends ActiveBuildScopedRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByWorkflowKey(string $workflowKey): ?array
    {
        $row = $this->scopeActiveBuild(
            $this->query('workflow_reliability_current')
        )
            ->where('workflow_key', $workflowKey)
            ->orderByDesc('updated_at')
            ->first([
                'workflow_key',
                'reliability_score',
                'degraded_rate',
                'hard_fail_rate',
                'escalation_events',
                'projection_build_id',
                'source_high_watermark_ingested_at',
                'updated_at',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'workflow_key' => (string) $row->workflow_key,
            'reliability_score' => isset($row->reliability_score) ? (float) $row->reliability_score : null,
            'degraded_rate' => isset($row->degraded_rate) ? (float) $row->degraded_rate : null,
            'hard_fail_rate' => isset($row->hard_fail_rate) ? (float) $row->hard_fail_rate : null,
            'escalation_events' => isset($row->escalation_events) ? (int) $row->escalation_events : null,
            'projection_build_id' => (string) $row->projection_build_id,
            'source_high_watermark_ingested_at' => $row->source_high_watermark_ingested_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
