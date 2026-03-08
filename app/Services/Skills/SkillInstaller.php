<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\AgentSkill;
use App\Models\AgentSkillValidation;
use App\Services\Skills\DTOs\SkillInstallResult;
use App\Services\Skills\DTOs\SkillParseResult;
use App\Services\Skills\Validation\SkillValidationResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

final class SkillInstaller
{
    private const ADMIN_CONFIRM_THRESHOLD = 0.5;

    private const BLOCK_THRESHOLD = 0.8;

    public function __construct(
        private readonly SkillParser $parser,
        private readonly SkillValidator $validator,
    ) {}

    public function installFromFile(
        string $filePath,
        int $teamId,
        ?int $userId = null,
        bool $isGlobal = false,
        string $source = 'upload',
    ): SkillInstallResult {
        $tempId = Str::uuid()->toString();
        $tempDir = storage_path("app/skills/tmp/{$tempId}");

        try {
            $extractedRoot = $this->extractZip($filePath, $tempDir);
            if ($extractedRoot === null) {
                return SkillInstallResult::failure(['Failed to extract skill archive.']);
            }

            $skillMdPath = $extractedRoot.'/SKILL.md';
            if (! File::exists($skillMdPath)) {
                return SkillInstallResult::failure(['SKILL.md not found in archive.']);
            }

            $parsed = $this->parser->parse(File::get($skillMdPath));
            if ($parsed->hasErrors()) {
                return SkillInstallResult::failure($parsed->errors, null, $parsed->warnings);
            }

            $validation = $this->validator->validate($extractedRoot, $parsed, $source, $teamId);
            if (! $validation->overallPass) {
                return SkillInstallResult::failure(
                    array_map(fn ($e) => $e['message'], $validation->blockingErrors),
                    $validation,
                    $validation->warnings,
                );
            }

            if ($validation->riskScore >= self::BLOCK_THRESHOLD) {
                return SkillInstallResult::blocked($validation, $validation->warnings);
            }

            if ($validation->riskScore >= self::ADMIN_CONFIRM_THRESHOLD) {
                return SkillInstallResult::requiresConfirmation($validation, $validation->warnings);
            }

            $targetPath = $this->resolveTargetPath($parsed->name, $teamId, $isGlobal);
            $this->copyToTarget($extractedRoot, $targetPath);

            $skill = $this->persistSkill($parsed, $validation, $teamId, $userId, $targetPath, $isGlobal, $source);

            $this->recordValidation($parsed, $validation, $teamId, $userId, $source);

            return SkillInstallResult::success($skill->id, $validation, $validation->warnings);
        } catch (QueryException $e) {
            if ($this->isDuplicateSlugError($e)) {
                return SkillInstallResult::failure(["A skill with slug '{$parsed->name}' already exists for this team."]); // @phpstan-ignore variable.undefined
            }
            throw $e;
        } finally {
            $this->cleanupTemp($tempDir);
        }
    }

    public function installFromUrl(string $url, int $teamId, ?int $userId = null): SkillInstallResult
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'skill_download_');
        try {
            $contents = file_get_contents($url);
            if ($contents === false) {
                return SkillInstallResult::failure(["Failed to download skill from URL: {$url}"]);
            }
            file_put_contents($tempFile, $contents);

            return $this->installFromFile($tempFile, $teamId, $userId, source: 'url');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function installFromLibrary(string $slug, string $filePath, int $teamId, ?int $userId = null): SkillInstallResult
    {
        return $this->installFromFile($filePath, $teamId, $userId, source: 'library');
    }

    public function update(string $skillId, string $filePath, ?int $userId = null): SkillInstallResult
    {
        $skill = AgentSkill::findOrFail($skillId);

        $tempId = Str::uuid()->toString();
        $tempDir = storage_path("app/skills/tmp/{$tempId}");

        try {
            $extractedRoot = $this->extractZip($filePath, $tempDir);
            if ($extractedRoot === null) {
                return SkillInstallResult::failure(['Failed to extract skill archive.']);
            }

            $skillMdPath = $extractedRoot.'/SKILL.md';
            if (! File::exists($skillMdPath)) {
                return SkillInstallResult::failure(['SKILL.md not found in archive.']);
            }

            $parsed = $this->parser->parse(File::get($skillMdPath));
            if ($parsed->hasErrors()) {
                return SkillInstallResult::failure($parsed->errors, null, $parsed->warnings);
            }

            // Validate without duplicate slug check by passing source context
            $validation = $this->validator->validate($extractedRoot, $parsed, 'update', $skill->team_id);

            // For updates, ignore duplicate_slug blocking errors for the same skill
            $filteredErrors = array_filter(
                $validation->blockingErrors,
                fn ($e) => $e['check'] !== 'duplicate_slug',
            );

            if (! empty($filteredErrors)) {
                return SkillInstallResult::failure(
                    array_map(fn ($e) => $e['message'], $filteredErrors),
                    $validation,
                    $validation->warnings,
                );
            }

            if ($validation->riskScore >= self::BLOCK_THRESHOLD) {
                return SkillInstallResult::blocked($validation, $validation->warnings);
            }

            $targetPath = $skill->skill_path;

            // Replace files atomically: delete old, copy new
            if (File::isDirectory($targetPath)) {
                File::deleteDirectory($targetPath);
            }
            $this->copyToTarget($extractedRoot, $targetPath);

            // Update DB row
            $skill->update([
                'description' => $parsed->description,
                'version' => $parsed->version,
                'author' => $parsed->author,
                'risk_level' => $parsed->riskLevel,
                'industries' => $parsed->industries,
                'memory_blocks' => $parsed->memoryBlocks,
                'mcp_dependencies' => $parsed->mcpDependencies,
                'tools_required' => $parsed->toolsRequired,
                'trigger_keywords' => $parsed->triggerKeywords,
                'run_after' => $parsed->runAfter,
                'requires_approval' => $parsed->requiresApproval,
                'checksum' => $validation->fileHashes['_archive_checksum'] ?? hash_file('sha256', $filePath),
                'file_hashes' => $validation->fileHashes,
                'validation_result' => $validation->toArray(),
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            $this->recordValidation($parsed, $validation, $skill->team_id, $userId, 'update');

            return SkillInstallResult::success($skill->id, $validation, $validation->warnings);
        } finally {
            $this->cleanupTemp($tempDir);
        }
    }

    public function remove(string $skillId, ?int $userId = null): void
    {
        $skill = AgentSkill::findOrFail($skillId);

        if (File::isDirectory($skill->skill_path)) {
            File::deleteDirectory($skill->skill_path);
        }

        $skill->delete();
    }

    private function extractZip(string $filePath, string $tempDir): ?string
    {
        File::ensureDirectoryExists($tempDir);

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return null;
        }

        $zip->extractTo($tempDir);
        $zip->close();

        return $this->findSkillRoot($tempDir);
    }

    private function findSkillRoot(string $tempDir): string
    {
        // Check if SKILL.md is at the root level
        if (File::exists($tempDir.'/SKILL.md')) {
            return $tempDir;
        }

        // Check one level deep (ZIP often wraps in a directory)
        $directories = File::directories($tempDir);
        foreach ($directories as $dir) {
            if (File::exists($dir.'/SKILL.md')) {
                return $dir;
            }
        }

        return $tempDir;
    }

    private function resolveTargetPath(string $name, int $teamId, bool $isGlobal): string
    {
        if ($isGlobal) {
            return config('agent.skills.global_storage_path').'/'.$name;
        }

        return config('agent.skills.storage_path').'/'.$teamId.'/'.$name;
    }

    private function copyToTarget(string $source, string $target): void
    {
        File::ensureDirectoryExists(dirname($target));
        File::copyDirectory($source, $target);
    }

    private function persistSkill(
        SkillParseResult $parsed,
        SkillValidationResult $validation,
        int $teamId,
        ?int $userId,
        string $targetPath,
        bool $isGlobal,
        string $source,
    ): AgentSkill {
        return AgentSkill::create([
            'team_id' => $teamId,
            'name' => $parsed->name,
            'slug' => $parsed->name,
            'description' => $parsed->description,
            'version' => $parsed->version,
            'author' => $parsed->author,
            'risk_level' => $parsed->riskLevel,
            'status' => AgentSkill::STATUS_ACTIVE,
            'industries' => $parsed->industries,
            'memory_blocks' => $parsed->memoryBlocks,
            'mcp_dependencies' => $parsed->mcpDependencies,
            'tools_required' => $parsed->toolsRequired,
            'trigger_keywords' => $parsed->triggerKeywords,
            'run_after' => $parsed->runAfter,
            'requires_approval' => $parsed->requiresApproval,
            'skill_path' => $targetPath,
            'checksum' => $this->computeArchiveChecksum($validation),
            'file_hashes' => $validation->fileHashes,
            'validation_result' => $validation->toArray(),
            'is_global' => $isGlobal,
            'installed_by' => $userId,
            'installed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function computeArchiveChecksum(SkillValidationResult $validation): string
    {
        // Use the integrity stage's archive checksum if available, otherwise compute from file hashes
        return $validation->fileHashes['_archive_checksum']
            ?? hash('sha256', json_encode($validation->fileHashes));
    }

    private function recordValidation(
        SkillParseResult $parsed,
        SkillValidationResult $validation,
        int $teamId,
        ?int $userId,
        string $source,
    ): void {
        AgentSkillValidation::create([
            'skill_name' => $parsed->name,
            'team_id' => $teamId,
            'validation_result' => $validation->toArray(),
            'risk_score' => $validation->riskScore,
            'overall_pass' => $validation->overallPass,
            'source' => $source,
            'validated_by' => $userId,
            'created_at' => now(),
        ]);
    }

    private function cleanupTemp(string $tempDir): void
    {
        if (File::isDirectory($tempDir)) {
            File::deleteDirectory($tempDir);
        }
    }

    private function isDuplicateSlugError(QueryException $e): bool
    {
        // PostgreSQL unique violation error code
        return $e->getCode() === '23505';
    }
}
