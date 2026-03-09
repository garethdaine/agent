<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisEvent;

class GetLatestEventSequenceAction
{
    public function execute(int $sessionId): int
    {
        return (int) (RepoAnalysisEvent::query()
            ->where('repo_analysis_session_id', $sessionId)
            ->max('sequence') ?? 0);
    }
}
