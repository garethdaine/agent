<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisSession;

class PurgeRepoAnalysisSessionAction
{
    public function execute(RepoAnalysisSession $session): bool
    {
        return (bool) $session->forceDelete();
    }
}
