<?php

declare(strict_types=1);

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

        // Trim any trailing incomplete UTF-8 multi-byte sequence caused by the
        // fixed-length read splitting a character at the boundary.
        $snippet = $this->trimIncompleteUtf8Tail($snippet);

        if (function_exists('mb_check_encoding') && ! mb_check_encoding($snippet, 'UTF-8')) {
            return 'The task_markdown_path must point to a UTF-8 text-like file.';
        }

        $allowedBases = $this->resolvedBases(config('agent.allowed_task_markdown_bases', []));

        if (! $this->isWithinAllowedBases($realPath, $allowedBases)) {
            return sprintf(
                'The task_markdown_path is outside the allowed base directories. Allowed: %s. Add more via AGENT_ADDITIONAL_TASK_MARKDOWN_BASES.',
                $this->formatAllowedBases($allowedBases)
            );
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
            return sprintf(
                'The working_directory is outside the allowed base directories. Allowed: %s. Add more via AGENT_ADDITIONAL_WORKING_DIRECTORY_BASES.',
                $this->formatAllowedBases($allowedBases)
            );
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
            if (! is_string($base) || $base === '') { // @phpstan-ignore function.alreadyNarrowedType
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

    /**
     * @param  array<int, string>  $allowedBases
     */
    private function formatAllowedBases(array $allowedBases): string
    {
        if ($allowedBases === []) {
            return '(none configured)';
        }

        return implode(', ', $allowedBases);
    }

    /**
     * Trim trailing bytes that form an incomplete UTF-8 multi-byte sequence.
     *
     * When reading a fixed number of bytes from a file, the read boundary may
     * land in the middle of a multi-byte character (e.g. 4096 bytes cuts an
     * em-dash 0xE2 0x80 0x94 after just 0xE2). This causes mb_check_encoding
     * to reject otherwise valid UTF-8 content.
     */
    private function trimIncompleteUtf8Tail(string $data): string
    {
        $len = strlen($data);
        if ($len === 0) {
            return $data;
        }

        // Walk backwards to find the start of the last character.
        // UTF-8 continuation bytes are 10xxxxxx (0x80..0xBF).
        $i = $len - 1;
        while ($i >= 0 && $i >= $len - 4 && (ord($data[$i]) & 0xC0) === 0x80) {
            $i--;
        }

        if ($i < 0 || $i < $len - 4) {
            return $data;
        }

        $leadByte = ord($data[$i]);
        $expectedLength = match (true) {
            ($leadByte & 0x80) === 0x00 => 1, // 0xxxxxxx — ASCII
            ($leadByte & 0xE0) === 0xC0 => 2, // 110xxxxx
            ($leadByte & 0xF0) === 0xE0 => 3, // 1110xxxx
            ($leadByte & 0xF8) === 0xF0 => 4, // 11110xxx
            default => 1, // Invalid lead byte, treat as single byte
        };

        $actualLength = $len - $i;

        if ($actualLength < $expectedLength) {
            return substr($data, 0, $i);
        }

        return $data;
    }
}
