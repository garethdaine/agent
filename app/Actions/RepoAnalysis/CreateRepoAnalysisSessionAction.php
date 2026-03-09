<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisSession;
use App\Support\RepoAnalysis\SessionStateTransitionService;

class CreateRepoAnalysisSessionAction
{
    /**
     * @param  array{name?: string|null, project_directory: string, analyzer_profile?: string, runner_type?: string}  $data
     */
    public function execute(int $userId, array $data): RepoAnalysisSession
    {
        /** @var RepoAnalysisSession */
        return RepoAnalysisSession::query()->create([
            'user_id' => $userId,
            'name' => array_key_exists('name', $data) ? ($data['name'] ?: null) : null,
            'project_directory' => (string) $data['project_directory'],
            'analyzer_profile' => (string) ($data['analyzer_profile'] ?? 'default'),
            'runner_type' => (string) ($data['runner_type'] ?? 'claude'),
            'status' => SessionStateTransitionService::STATUS_SETUP,
            'phase' => 0,
            'metadata_json' => [
                'source' => 'api',
                'auto_start_on_open' => true,
            ],
        ]);
    }
}
