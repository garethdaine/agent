<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisSession;

class DeleteRepoAnalysisSessionAction
{
    public function execute(RepoAnalysisSession $session): bool
    {
        return (bool) $session->delete();
    }
}
