<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FormatValidator
{
    public function validate(string $extractedPath): StageResult
    {
        $failures = [];
        $checks = 0;

        // Check SKILL.md exists
        $checks++;
        if (! file_exists($extractedPath.'/SKILL.md')) {
            $failures[] = [
                'check' => 'skill_md_exists',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => 'SKILL.md not found in skill directory.',
            ];

            return StageResult::fail('format', $failures, $checks);
        }

        // Check path traversal
        $checks++;
        $realExtracted = realpath($extractedPath);
        if ($realExtracted === false) {
            $failures[] = [
                'check' => 'path_traversal',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => 'Extracted path does not resolve to a valid directory.',
            ];

            return StageResult::fail('format', $failures, $checks);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractedPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $totalSize = 0;
        $fileCount = 0;
        $maxSize = (int) config('agent.skills.max_skill_archive_size_bytes', 10 * 1024 * 1024);
        $maxFiles = (int) config('agent.skills.max_skill_file_count', 50);

        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            $realFilePath = realpath($filePath);

            // Symlink check
            $checks++;
            if (is_link($filePath)) {
                $failures[] = [
                    'check' => 'symlinks',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Symlink detected: {$filePath}",
                ];

                return StageResult::fail('format', $failures, $checks);
            }

            // Path traversal check - ensure all files resolve within extracted path
            if ($realFilePath !== false && ! str_starts_with($realFilePath, $realExtracted)) {
                $failures[] = [
                    'check' => 'path_traversal',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Path traversal detected: {$filePath}",
                ];

                return StageResult::fail('format', $failures, $checks);
            }

            // Check for path traversal in the relative path
            $relativePath = str_replace($extractedPath, '', $filePath);
            if (str_contains($relativePath, '..')) {
                $failures[] = [
                    'check' => 'path_traversal',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Path traversal pattern detected: {$relativePath}",
                ];

                return StageResult::fail('format', $failures, $checks);
            }

            if ($file->isFile()) {
                $fileCount++;
                $totalSize += $file->getSize();
            }
        }

        // Total size check
        $checks++;
        if ($totalSize > $maxSize) {
            $failures[] = [
                'check' => 'total_size',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => "Total size {$totalSize} bytes exceeds limit of {$maxSize} bytes.",
            ];

            return StageResult::fail('format', $failures, $checks);
        }

        // File count check
        $checks++;
        if ($fileCount > $maxFiles) {
            $failures[] = [
                'check' => 'file_count',
                'severity' => StageResult::SEVERITY_BLOCK,
                'message' => "File count {$fileCount} exceeds limit of {$maxFiles}.",
            ];

            return StageResult::fail('format', $failures, $checks);
        }

        return StageResult::pass('format', $checks);
    }
}
