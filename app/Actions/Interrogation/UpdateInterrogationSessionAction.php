<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSession;
use Carbon\CarbonImmutable;

class UpdateInterrogationSessionAction
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(InterrogationSession $session, array $validated): InterrogationSession
    {
        if (array_key_exists('name', $validated)) {
            $session->name = $validated['name'];
        }

        if (array_key_exists('feature_brief', $validated)) {
            $session->feature_brief = $validated['feature_brief'];
        }

        if (array_key_exists('model', $validated)) {
            $session->model = $validated['model'];
        }

        if (array_key_exists('git', $validated)) {
            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $existingGit = is_array($metadata['git'] ?? null) ? $metadata['git'] : [];
            $incomingGit = is_array($validated['git']) ? $validated['git'] : [];
            $metadata['git'] = $this->normalizeGitSettings(array_merge($existingGit, $incomingGit));
            $session->metadata_json = $metadata;
        }

        if (array_key_exists('build_settings', $validated)) {
            $metadata = is_array($session->metadata_json) ? $session->metadata_json : [];
            $existing = is_array($metadata['build_settings'] ?? null) ? $metadata['build_settings'] : [];
            $incoming = is_array($validated['build_settings']) ? $validated['build_settings'] : [];
            $metadata['build_settings'] = $this->normalizeBuildSettings(array_merge($existing, $incoming));
            $session->metadata_json = $metadata;
        }

        $session->save();

        return $session;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeGitSettings(array $raw): array
    {
        return [
            'commit_enabled' => (bool) ($raw['commit_enabled'] ?? false),
            'conventional_commits' => (bool) ($raw['conventional_commits'] ?? false),
            'worktree_enabled' => (bool) ($raw['worktree_enabled'] ?? false),
            'branching_enabled' => (bool) ($raw['branching_enabled'] ?? false),
            'branch_prefix' => is_string($raw['branch_prefix'] ?? null) && trim($raw['branch_prefix']) !== ''
                ? trim($raw['branch_prefix'])
                : null,
            'target_branch' => is_string($raw['target_branch'] ?? null) && trim($raw['target_branch']) !== ''
                ? trim($raw['target_branch'])
                : null,
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeBuildSettings(array $raw): array
    {
        return [
            'auto_advance_tasks' => (bool) ($raw['auto_advance_tasks'] ?? true),
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }
}
