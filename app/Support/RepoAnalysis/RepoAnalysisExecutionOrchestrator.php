<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Jobs\RepoAnalysis\ExecuteRepoAnalysisTaskJob;
use App\Jobs\RepoAnalysis\GenerateRepoAnalysisReportJob;
use App\Jobs\RepoAnalysis\PlanRepoAnalysisTasksJob;
use App\Jobs\RepoAnalysis\ValidateRepoAnalysisCoverageJob;
use App\Models\RepoAnalysisSession;
use App\Models\RepoAnalysisTask;
use App\Support\RepoAnalysis\Exceptions\QueueMisroutingException;

class RepoAnalysisExecutionOrchestrator
{
    public function connection(): string
    {
        $connection = config('repo_analysis.queue.connection', 'redis');

        return is_string($connection) && $connection !== ''
            ? $connection
            : 'redis';
    }

    public function queue(): string
    {
        $queue = config('repo_analysis.queue.name', 'repo-analysis');

        return is_string($queue) && $queue !== ''
            ? $queue
            : 'repo-analysis';
    }

    public function assertQueue(?string $actualQueue): void
    {
        $queue = $actualQueue ?? '';
        if ($queue === $this->queue()) {
            return;
        }

        throw new QueueMisroutingException(sprintf(
            'Repo analysis job routed to [%s]; expected [%s].',
            $queue !== '' ? $queue : 'null',
            $this->queue(),
        ));
    }

    public function dispatchPlan(int $sessionId, bool $dispatchNext = true): void
    {
        if ($this->runInline()) {
            PlanRepoAnalysisTasksJob::dispatchSync($sessionId, $dispatchNext);

            return;
        }

        PlanRepoAnalysisTasksJob::dispatch($sessionId, $dispatchNext)
            ->onConnection($this->connection())
            ->onQueue($this->queue());
    }

    public function dispatchExecute(int $sessionId, bool $dispatchNext = true): void
    {
        if ($this->runInline()) {
            ExecuteRepoAnalysisTaskJob::dispatchSync($sessionId, $dispatchNext);

            return;
        }

        ExecuteRepoAnalysisTaskJob::dispatch($sessionId, $dispatchNext)
            ->onConnection($this->connection())
            ->onQueue($this->queue());
    }

    public function dispatchValidate(int $sessionId, bool $dispatchNext = true): void
    {
        if ($this->runInline()) {
            ValidateRepoAnalysisCoverageJob::dispatchSync($sessionId, $dispatchNext);

            return;
        }

        ValidateRepoAnalysisCoverageJob::dispatch($sessionId, $dispatchNext)
            ->onConnection($this->connection())
            ->onQueue($this->queue());
    }

    public function dispatchReport(int $sessionId): void
    {
        if ($this->runInline()) {
            GenerateRepoAnalysisReportJob::dispatchSync($sessionId);

            return;
        }

        GenerateRepoAnalysisReportJob::dispatch($sessionId)
            ->onConnection($this->connection())
            ->onQueue($this->queue());
    }

    public function snapshot(array $metadata): array
    {
        $snapshot = $metadata['snapshot'] ?? null;

        return is_array($snapshot) ? $snapshot : [];
    }

    public function taskInputHash(string $snapshotHash, string $analyzerName, string $analyzerVersion): string
    {
        return hash('sha256', implode('|', [$snapshotHash, $analyzerName, $analyzerVersion]));
    }

    public function consumeFailureDirective(RepoAnalysisTask $task): ?string
    {
        $metadata = is_array($task->metadata_json) ? $task->metadata_json : [];
        $script = $metadata['failure_script'] ?? null;
        if (! is_array($script) || $script === []) {
            return null;
        }

        $directive = array_shift($script);
        $metadata['failure_script'] = array_values($script);
        $task->metadata_json = $metadata;
        $task->save();

        return is_string($directive) && $directive !== ''
            ? $directive
            : null;
    }

    public function markOperatorDecisionRequired(RepoAnalysisSession $session, string $requirement, array $details = []): void
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $metadata['operator_action_required'] = $requirement;

        if ($details !== []) {
            $metadata['operator_action_details'] = $details;
        }

        $session->metadata_json = $metadata;
        $session->save();
    }

    public function operatorDecision(RepoAnalysisSession $session, string $key): ?string
    {
        $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
        $decision = $metadata[$key] ?? null;

        return is_string($decision) && $decision !== '' ? $decision : null;
    }

    private function runInline(): bool
    {
        return app()->runningUnitTests();
    }
}
