<?php

declare(strict_types=1);

namespace App\Actions\RepoAnalysis;

use App\Models\RepoAnalysisSession;

class UpdateRepoAnalysisSessionAction
{
    /**
     * @param  array{name?: string|null, project_directory?: string, analyzer_profile?: string, runner_type?: string}  $data
     * @return array{session: RepoAnalysisSession, changed_fields: list<string>, before: array<string, mixed>}
     */
    public function execute(RepoAnalysisSession $session, array $data): array
    {
        $before = $session->only(['name', 'project_directory', 'analyzer_profile', 'runner_type']);

        if (array_key_exists('name', $data)) {
            $session->name = $data['name'] ?: null;
        }

        if (array_key_exists('project_directory', $data)) {
            $session->project_directory = (string) $data['project_directory'];
        }

        if (array_key_exists('analyzer_profile', $data)) {
            $session->analyzer_profile = $data['analyzer_profile'] ?: 'default';
        }

        if (array_key_exists('runner_type', $data)) {
            $session->runner_type = in_array($data['runner_type'], ['claude', 'codex'], true)
                ? $data['runner_type']
                : 'claude';
        }

        $session->save();

        $changedFields = [];
        foreach (['name', 'project_directory', 'analyzer_profile', 'runner_type'] as $field) {
            if (data_get($before, $field) !== data_get($session, $field)) {
                $changedFields[] = $field;
            }
        }

        return [
            'session' => $session,
            'changed_fields' => $changedFields,
            'before' => $before,
        ];
    }
}
