<?php

declare(strict_types=1);

namespace App\Jobs\RepoAnalysis;

use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use App\Support\RepoAnalysis\EventWriter;
use App\Support\RepoAnalysis\ExportService;
use App\Support\RepoAnalysis\ReportComposer;
use App\Support\RepoAnalysis\RepoAnalysisExecutionOrchestrator;
use App\Support\RepoAnalysis\SessionStateTransitionService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateRepoAnalysisReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $sessionId)
    {
        $this->onConnection(config('repo_analysis.queue.connection', 'redis'));
        $this->onQueue(config('repo_analysis.queue.name', 'repo-analysis'));
    }

    public function handle(
        RepoAnalysisExecutionOrchestrator $orchestrator,
        SessionStateTransitionService $transitions,
        ReportComposer $reportComposer,
        ExportService $exportService,
    ): void {
        $orchestrator->assertQueue($this->queue);

        $session = RepoAnalysisSession::query()->find($this->sessionId);
        if ($session === null) {
            return;
        }

        if ((int) $session->phase < 5) {
            return;
        }

        try {
            $coverageSummary = data_get($session->report_summary_json, 'coverage', []);
            $composition = $reportComposer->compose(
                $session,
                is_array($coverageSummary) ? $coverageSummary : []
            );

            $report = RepoAnalysisReport::query()->updateOrCreate(
                [
                    'repo_analysis_session_id' => $session->id,
                    'report_hash' => (string) $composition['report_hash'],
                ],
                [
                    'report_version' => (string) $composition['report_version'],
                    'status' => 'generated',
                    'payload_json' => $composition['payload_json'],
                    'metadata_json' => $composition['metadata_json'],
                    'generated_at' => CarbonImmutable::now('UTC'),
                    'error_code' => null,
                    'error_summary' => null,
                ]
            );

            $exportPaths = $exportService->export($session, $report);

            $session->report_summary_json = array_merge(
                is_array($session->report_summary_json) ? $session->report_summary_json : [],
                [
                    'report_hash' => (string) $composition['report_hash'],
                    'report_version' => (string) $composition['report_version'],
                    'markdown_export_path' => $exportPaths['markdown_export_path'],
                    'json_export_path' => $exportPaths['json_export_path'],
                ]
            );
            $session->error_code = null;
            $session->error_summary = null;
            $session->save();

            $writer = new EventWriter($session);
            $writer->append('report_generated', [
                'report_hash' => (string) $composition['report_hash'],
                'artifact_count' => (int) data_get($composition['payload_json'], 'artifact_count', 0),
                'markdown_export_path' => $exportPaths['markdown_export_path'],
                'json_export_path' => $exportPaths['json_export_path'],
            ], [
                'phase' => 5,
                'status' => SessionStateTransitionService::STATUS_REPORTING,
            ]);

            $transitioned = $transitions->transitionTo($session->id, 6, SessionStateTransitionService::STATUS_COMPLETED, [
                'finished_at' => CarbonImmutable::now('UTC'),
                'error_code' => null,
                'error_summary' => null,
            ]);

            if (! $transitioned) {
                $session->phase = 6;
                $session->status = SessionStateTransitionService::STATUS_COMPLETED;
                $session->finished_at = CarbonImmutable::now('UTC');
                $session->error_code = null;
                $session->error_summary = null;
                $session->save();
            }
        } catch (Throwable $throwable) {
            $errorCode = $throwable instanceof \InvalidArgumentException
                ? 'REPORT_EXPORT_POLICY_VIOLATION'
                : 'REPORT_GENERATION_FAILED';

            $transitions->pause($session->id, [
                'error_code' => $errorCode,
                'error_summary' => $throwable->getMessage() !== ''
                    ? $throwable->getMessage()
                    : 'Report generation failed.',
            ]);
        }
    }
}
