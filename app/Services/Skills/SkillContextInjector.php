<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Services\Skills\DTOs\ResolvedSkill;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SkillContextInjector
{
    /**
     * Build metadata block from resolved skills and inject into content.
     *
     * @param  ResolvedSkill[]  $resolvedSkills
     */
    public function injectMetadata(array $resolvedSkills, string $existingContent): string
    {
        if (empty($resolvedSkills)) {
            return $existingContent;
        }

        $block = "## Available Skills\n";

        foreach ($resolvedSkills as $skill) {
            $keywords = implode(', ', $skill->triggerKeywords);
            $block .= "\n### {$skill->name}\n";
            $block .= "{$skill->description}\n";
            $block .= "Keywords: {$keywords}\n";
            $block .= "\n---\n";
        }

        return $block."\n".$existingContent;
    }

    /**
     * Load full instructions from a skill's SKILL.md file (body only, skip frontmatter).
     */
    public function loadFullInstructions(string $skillPath): string
    {
        $skillMdPath = rtrim($skillPath, '/').'/SKILL.md';

        if (! File::exists($skillMdPath)) {
            Log::warning('SKILL.md not found at path', ['path' => $skillMdPath]);

            return '';
        }

        $content = File::get($skillMdPath);

        return $this->stripFrontmatter($content);
    }

    /**
     * Load a resource file from within a skill directory.
     */
    public function loadResource(string $skillPath, string $resourcePath): string
    {
        $fullPath = rtrim($skillPath, '/').'/'.ltrim($resourcePath, '/');

        // Validate path doesn't escape skill directory
        $realSkillPath = realpath($skillPath);
        $realFullPath = realpath($fullPath);

        if ($realSkillPath === false || $realFullPath === false) {
            Log::warning('Resource path resolution failed', [
                'skill_path' => $skillPath,
                'resource_path' => $resourcePath,
            ]);

            return '';
        }

        if (! str_starts_with($realFullPath, $realSkillPath)) {
            Log::warning('Resource path escapes skill directory', [
                'skill_path' => $skillPath,
                'resource_path' => $resourcePath,
            ]);

            return '';
        }

        return File::get($fullPath);
    }

    private function stripFrontmatter(string $content): string
    {
        if (! str_starts_with(trim($content), '---')) {
            return $content;
        }

        $parts = preg_split('/^---\s*$/m', $content, 3);

        if ($parts === false || count($parts) < 3) {
            return $content;
        }

        return trim($parts[2]);
    }
}
