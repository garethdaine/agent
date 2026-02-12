<?php

namespace App\Support\Agent;

class PathPolicy
{
    public function validateTaskMarkdownPath(?string $path): ?string
    {
        $error = $this->validateAbsolutePath($path, false);

        if ($error !== null) {
            return $error;
        }

        $realPath = realpath($path);

        if ($realPath === false || ! is_file($realPath)) {
            return 'The task_markdown_path must be an existing regular file.';
        }

        if (! is_readable($realPath)) {
            return 'The task_markdown_path must be readable.';
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['md', 'markdown'], true)) {
            return 'The task_markdown_path must end with .md or .markdown.';
        }

        $snippet = @file_get_contents($realPath, false, null, 0, 4096);

        if ($snippet === false) {
            return 'The task_markdown_path file could not be read.';
        }

        if (str_contains($snippet, "\0")) {
            return 'The task_markdown_path must point to a UTF-8 text-like file.';
        }

        if (function_exists('mb_check_encoding') && ! mb_check_encoding($snippet, 'UTF-8')) {
            return 'The task_markdown_path must point to a UTF-8 text-like file.';
        }

        $allowedBases = $this->resolvedBases(config('agent.allowed_task_markdown_bases', []));

        if (! $this->isWithinAllowedBases($realPath, $allowedBases)) {
            return 'The task_markdown_path is outside the allowed base directories.';
        }

        return null;
    }

    public function validateWorkingDirectory(?string $path): ?string
    {
        $error = $this->validateAbsolutePath($path, true);

        if ($error !== null) {
            return $error;
        }

        $realPath = realpath($path);

        if ($realPath === false || ! is_dir($realPath)) {
            return 'The working_directory must be an existing directory.';
        }

        if (! is_readable($realPath) || ! is_executable($realPath)) {
            return 'The working_directory must be readable and executable.';
        }

        $allowedBases = $this->resolvedBases(config('agent.allowed_working_directory_bases', []));

        if (! $this->isWithinAllowedBases($realPath, $allowedBases)) {
            return 'The working_directory is outside the allowed base directories.';
        }

        return null;
    }

    private function validateAbsolutePath(?string $path, bool $directory): ?string
    {
        if (! is_string($path) || $path === '') {
            return $directory
                ? 'The working_directory is required.'
                : 'The task_markdown_path is required.';
        }

        if (strlen($path) > 1024) {
            return $directory
                ? 'The working_directory may not be greater than 1024 characters.'
                : 'The task_markdown_path may not be greater than 1024 characters.';
        }

        if ($path[0] !== '/') {
            return $directory
                ? 'The working_directory must be an absolute path.'
                : 'The task_markdown_path must be an absolute path.';
        }

        if (realpath($path) === false) {
            return $directory
                ? 'The working_directory path could not be resolved.'
                : 'The task_markdown_path path could not be resolved.';
        }

        return null;
    }

    /**
     * @param  array<int, string>  $bases
     * @return array<int, string>
     */
    private function resolvedBases(array $bases): array
    {
        $resolved = [];

        foreach ($bases as $base) {
            if (! is_string($base) || $base === '') {
                continue;
            }

            $realBase = realpath($base);

            if ($realBase !== false) {
                $resolved[] = rtrim($realBase, DIRECTORY_SEPARATOR);
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  array<int, string>  $allowedBases
     */
    private function isWithinAllowedBases(string $realPath, array $allowedBases): bool
    {
        $normalizedPath = rtrim($realPath, DIRECTORY_SEPARATOR);

        foreach ($allowedBases as $base) {
            if ($normalizedPath === $base || str_starts_with($normalizedPath, $base.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
