<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisReport;
use Illuminate\Database\Eloquent\Collection;

class ListRepoAnalysisReportsAction
{
    /**
     * @return Collection<int, RepoAnalysisReport>
     */
    public function execute(int $sessionId, int $limit = 100): Collection
    {
        return RepoAnalysisReport::query()
            ->where('repo_analysis_session_id', $sessionId)
            ->latest('id')
            ->limit(min(200, max(1, $limit)))
            ->get();
    }
}
