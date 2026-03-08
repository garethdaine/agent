<?php

declare(strict_types=1);

namespace App\Jobs\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use App\Models\RepoAnalysisSession;
use App\Support\RepoAnalysis\CoverageGateService;
use App\Support\RepoAnalysis\EventWriter;
use App\Support\RepoAnalysis\RepoAnalysisExecutionOrchestrator;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ValidateRepoAnalysisCoverageJob implements ShouldQueue
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
        RepoAnalysisExecutionOrchestrator $orchestrator,
        SessionStateTransitionService $transitions,
        CoverageGateService $coverageGateService,
    ): void {
        $orchestrator->assertQueue($this->queue);

        $session = RepoAnalysisSession::query()->find($this->sessionId);
        if ($session === null) {
            return;
        }

        if ((int) $session->phase >= 5) {
            if ($this->dispatchNext) {
                $orchestrator->dispatchReport($session->id);
            }

            return;
        }

        if ((int) $session->phase === 4 && (string) $session->status === SessionStateTransitionService::STATUS_PAUSED) {
            $transitions->resume($session->id);
            $session->refresh();
        }

        if ((int) $session->phase !== 4 || (string) $session->status !== SessionStateTransitionService::STATUS_VALIDATING) {
            return;
        }

        $summary = $coverageGateService->evaluate($session);

        RepoAnalysisArtifact::query()->updateOrCreate(
            [
                'repo_analysis_session_id' => $session->id,
                'artifact_key' => 'coverage.validation.json',
            ],
            [
                'repo_analysis_task_id' => null,
                'artifact_type' => 'coverage_validation',
                'content_hash' => hash('sha256', json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'),
                'schema_version' => '1.0.0',
                'analyzer_version' => 'coverage-gate-1.0.0',
                'payload_json' => $summary,
                'metadata_json' => [],
                'error_code' => null,
                'error_summary' => null,
            ]
        );

        $session->report_summary_json = [
            'coverage' => $summary,
        ];
        $session->save();

        $writer = new EventWriter($session);
        $writer->append('coverage_validated', $summary, [
            'phase' => 4,
            'status' => SessionStateTransitionService::STATUS_VALIDATING,
        ]);

        if (($summary['passed'] ?? false) !== true) { // @phpstan-ignore nullCoalesce.offset
            $firstFailure = $summary['blocking_failures'][0] ?? null;
            $errorCode = is_array($firstFailure) ? strtoupper((string) ($firstFailure['code'] ?? '')) : 'COVERAGE_GATE_FAILED';
            $errorSummary = is_array($firstFailure)
                ? (string) ($firstFailure['message'] ?? 'Coverage validation failed.')
                : 'Coverage validation failed.';

            $transitions->pause($session->id, [
                'error_code' => $errorCode !== '' ? $errorCode : 'COVERAGE_GATE_FAILED',
                'error_summary' => $errorSummary,
            ]);

            return;
        }

        $transitions->transitionTo($session->id, 5, SessionStateTransitionService::STATUS_REPORTING);

        if ($this->dispatchNext) {
            $orchestrator->dispatchReport($session->id);
        }
    }
}
