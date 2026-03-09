<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisTask;
use Illuminate\Database\Eloquent\Collection;

class ListRepoAnalysisTasksAction
{
    /**
     * @return Collection<int, RepoAnalysisTask>
     */
    public function execute(int $sessionId, int $limit = 100): Collection
    {
        return RepoAnalysisTask::query()
            ->where('repo_analysis_session_id', $sessionId)
            ->orderBy('id')
            ->limit(min(200, max(1, $limit)))
            ->get();
    }
}
