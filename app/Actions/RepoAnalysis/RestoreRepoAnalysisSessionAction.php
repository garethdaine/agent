<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisSession;

class RestoreRepoAnalysisSessionAction
{
    public function execute(RepoAnalysisSession $session): bool
    {
        if ($session->trashed()) {
            return (bool) $session->restore();
        }

        return false;
    }
}
