<?php

declare(strict_types=1);

namespace App\Jobs\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Support\RepoAnalysis\EventWriter;
use App\Support\RepoAnalysis\RepoAnalysisExecutionOrchestrator;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use App\Support\RepoAnalysis\SnapshotBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateRepoSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int $sessionId,
        public bool $dispatchNext = true,
    ) {
        $this->onConnection(config('repo_analysis.queue.connection', 'redis'));
        $this->onQueue(config('repo_analysis.queue.name', 'code-analysis'));
    }

    public function handle(
        SessionStateTransitionService $transitions,
        SnapshotBuilder $snapshotBuilder,
        RepoAnalysisExecutionOrchestrator $orchestrator,
    ): void {
        $orchestrator->assertQueue($this->queue);

        $session = RepoAnalysisSession::query()->find($this->sessionId);
        if ($session === null) {
            return;
        }

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $existingSnapshot = $orchestrator->snapshot($metadata);

        if ((int) $session->phase >= 2 && is_string($session->snapshot_hash) && $session->snapshot_hash !== '' && $existingSnapshot !== []) {
            if ($this->dispatchNext) {
                $orchestrator->dispatchPlan($session->id);
            }

            return;
        }

        if ((int) $session->phase === 0 && (string) $session->status === SessionStateTransitionService::STATUS_SETUP) {
            $transitions->transitionTo($session->id, 1, SessionStateTransitionService::STATUS_SNAPSHOTTING, [
                'started_at' => $session->started_at ?? CarbonImmutable::now('UTC'),
                'error_code' => null,
                'error_summary' => null,
            ]);
            $session->refresh();
        }

        if ((int) $session->phase !== 1 || (string) $session->status !== SessionStateTransitionService::STATUS_SNAPSHOTTING) {
            return;
        }

        $snapshot = $snapshotBuilder->build((string) $session->project_directory);

        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['snapshot'] = $snapshot;

        $session->snapshot_hash = (string) $snapshot['snapshot_hash'];
        $session->manifest_stats_json = is_array(data_get($snapshot, 'manifest.stats'))
            ? data_get($snapshot, 'manifest.stats')
            : [];
        $session->metadata_json = $metadata;
        $session->error_code = null;
        $session->error_summary = null;
        $session->save();

        RepoAnalysisArtifact::query()->updateOrCreate(
            [
                'repo_analysis_session_id' => $session->id,
                'artifact_key' => 'snapshot.manifest.json',
            ],
            [
                'repo_analysis_task_id' => null,
                'artifact_type' => 'snapshot_manifest',
                'content_hash' => (string) $snapshot['snapshot_hash'],
                'schema_version' => '1.0.0',
                'analyzer_version' => 'snapshot-builder-1.0.0',
                'payload_json' => is_array($snapshot['manifest'] ?? null) ? $snapshot['manifest'] : [],
                'metadata_json' => [
                    'manifest_json' => $snapshot['manifest_json'] ?? null,
                    'hash_input_json' => $snapshot['hash_input_json'] ?? null,
                ],
                'error_code' => null,
                'error_summary' => null,
            ]
        );

        $writer = new EventWriter($session);
        $writer->append('snapshot_generated', [
            'snapshot_hash' => (string) $snapshot['snapshot_hash'],
            'stats' => $session->manifest_stats_json,
        ], [
            'phase' => 1,
            'status' => SessionStateTransitionService::STATUS_SNAPSHOTTING,
        ]);

        $transitions->transitionTo($session->id, 2, SessionStateTransitionService::STATUS_PLANNING);

        if ($this->dispatchNext) {
            $orchestrator->dispatchPlan($session->id);
        }
    }
}
