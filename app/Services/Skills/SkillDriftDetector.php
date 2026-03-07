<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\AgentSkill;
use App\Services\Skills\DTOs\DriftCheckResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class SkillDriftDetector
{
    public function checkOnInvocation(AgentSkill $skill): DriftCheckResult
    {
        $currentHashes = $this->computeFileHashes($skill->skill_path);
        $storedHashes = $skill->file_hashes ?? [];

        return $this->compareHashes($skill, $storedHashes, $currentHashes);
    }

    /**
     * @return Collection<int, DriftCheckResult>
     */
    public function checkAll(?int $teamId = null): Collection
    {
        $query = AgentSkill::query()->active();

        if ($teamId !== null) {
            $query->where(function ($q) use ($teamId) {
                $q->forTeam($teamId)->orWhere(fn ($q2) => $q2->global());
            });
        }

        return $query->get()->map(fn (AgentSkill $skill) => $this->checkOnInvocation($skill));
    }

    /**
     * @return array<string, string>
     */
    public function computeFileHashes(string $skillPath): array
    {
        $hashes = [];

        if (! File::isDirectory($skillPath)) {
            return $hashes;
        }

        $files = File::allFiles($skillPath);

        foreach ($files as $file) {
            $relativePath = ltrim(str_replace($skillPath, '', $file->getPathname()), '/');
            $hashes[$relativePath] = hash_file('sha256', $file->getPathname());
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @param  array<string, string>  $stored
     * @param  array<string, string>  $current
     */
    private function compareHashes(AgentSkill $skill, array $stored, array $current): DriftCheckResult
    {
        $changed = [];
        $added = [];
        $missing = [];

        // Check for modified and missing files
        foreach ($stored as $path => $hash) {
            if (! isset($current[$path])) {
                $missing[] = $path;
            } elseif ($current[$path] !== $hash) {
                $changed[] = $path;
            }
        }

        // Check for added files
        foreach ($current as $path => $hash) {
            if (! isset($stored[$path])) {
                $added[] = $path;
            }
        }

        $hasDrift = ! empty($changed) || ! empty($added) || ! empty($missing);

        return new DriftCheckResult(
            skillId: (string) $skill->id,
            skillName: $skill->name,
            hasDrift: $hasDrift,
            changedFiles: $changed,
            addedFiles: $added,
            missingFiles: $missing,
        );
    }
}
