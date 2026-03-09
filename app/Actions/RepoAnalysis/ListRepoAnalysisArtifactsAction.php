<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisArtifact;
use Illuminate\Database\Eloquent\Collection;

class ListRepoAnalysisArtifactsAction
{
    /**
     * @return Collection<int, RepoAnalysisArtifact>
     */
    public function execute(int $sessionId, int $limit = 100): Collection
    {
        return RepoAnalysisArtifact::query()
            ->where('repo_analysis_session_id', $sessionId)
            ->orderBy('id')
            ->limit(min(200, max(1, $limit)))
            ->get();
    }
}
