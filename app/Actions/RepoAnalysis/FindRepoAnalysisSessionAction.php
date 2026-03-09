<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisSession;

class FindRepoAnalysisSessionAction
{
    public function execute(int $id, bool $withTrashed = false): RepoAnalysisSession
    {
        $query = RepoAnalysisSession::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        /** @var RepoAnalysisSession */
        return $query->findOrFail($id);
    }
}
