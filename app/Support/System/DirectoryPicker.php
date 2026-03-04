<?php

namespace App\Support\System;

use Symfony\Component\Process\Process;

class DirectoryPicker
{
    public function pick(?string $currentPath = null): string
    {
        $process = $this->buildProcess($this->normalizeCurrentPath($currentPath));
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->throwForFailure($process);
        }

        $selectedPath = $this->normalizeSelectedPath($process->getOutput());

        if ($selectedPath === null) {
            throw new DirectoryPickerException(
                errorCode: 'DIRECTORY_PICKER_FAILED',
                message: 'Directory picker returned no path.',
                statusCode: 500
            );
        }

        return $selectedPath;
    }

    private function buildProcess(?string $currentPath): Process
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => $this->buildMacProcess($currentPath),
            'Linux' => $this->buildLinuxProcess($currentPath),
            'Windows' => $this->buildWindowsProcess($currentPath),
            default => throw new DirectoryPickerException(
                errorCode: 'DIRECTORY_PICKER_UNSUPPORTED',
                message: 'Directory picker is not supported on this operating system.',
                statusCode: 501
            ),
        };
    }

    private function buildMacProcess(?string $currentPath): Process
    {
        $prompt = 'Select directory';

        if ($currentPath !== null) {
            $escapedPath = $this->escapeAppleScript($currentPath);
            $script = <<<APPLESCRIPT
set selectedFolder to choose folder with prompt "{$prompt}" default location POSIX file "{$escapedPath}"
POSIX path of selectedFolder
APPLESCRIPT;
        } else {
            $script = <<<APPLESCRIPT
set selectedFolder to choose folder with prompt "{$prompt}"
POSIX path of selectedFolder
APPLESCRIPT;
        }

        return new Process(['osascript', '-e', $script]);
    }

    private function buildLinuxProcess(?string $currentPath): Process
    {
        if ($this->binaryExists('zenity')) {
            return new Process([
                'zenity',
                '--file-selection',
                '--directory',
                '--title=Select directory',
                '--filename='.($currentPath ? rtrim($currentPath, '/').'/' : '/'),
            ]);
        }

        if ($this->binaryExists('kdialog')) {
            return new Process([
                'kdialog',
                '--getexistingdirectory',
                $currentPath ?: '/',
            ]);
        }

        throw new DirectoryPickerException(
            errorCode: 'DIRECTORY_PICKER_UNSUPPORTED',
            message: 'No supported Linux directory picker was found (zenity or kdialog).',
            statusCode: 501
        );
    }

    private function buildWindowsProcess(?string $currentPath): Process
    {
        if (! $this->binaryExists('powershell') && ! $this->binaryExists('powershell.exe')) {
            throw new DirectoryPickerException(
                errorCode: 'DIRECTORY_PICKER_UNSUPPORTED',
                message: 'PowerShell is required for directory picker on Windows.',
                statusCode: 501
            );
        }

        $selectedPath = $currentPath !== null
            ? '$dialog.SelectedPath = "'.$this->escapePowerShellString($currentPath).'";'
            : '';

        $script = <<<POWERSHELL
Add-Type -AssemblyName System.Windows.Forms;
\$dialog = New-Object System.Windows.Forms.FolderBrowserDialog;
{$selectedPath}
if (\$dialog.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) {
    Write-Output \$dialog.SelectedPath;
}
POWERSHELL;

        return new Process(['powershell', '-NoProfile', '-STA', '-Command', $script]);
    }

    private function normalizeCurrentPath(?string $currentPath): ?string
    {
        if (! is_string($currentPath)) {
            return null;
        }

        $trimmedPath = trim($currentPath);
        if ($trimmedPath === '') {
            return null;
        }

        $resolvedPath = realpath($trimmedPath);

        if ($resolvedPath === false || ! is_dir($resolvedPath)) {
            return null;
        }

        return $resolvedPath;
    }

    private function normalizeSelectedPath(string $rawOutput): ?string
    {
        $path = trim($rawOutput);

        if ($path === '') {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $path = rtrim($path, '\\/');
            if (preg_match('/^[A-Za-z]:$/', $path) === 1) {
                $path .= '\\';
            }
        } elseif ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $resolvedPath = realpath($path);

        if ($resolvedPath === false || ! is_dir($resolvedPath)) {
            throw new DirectoryPickerException(
                errorCode: 'DIRECTORY_PICKER_INVALID_PATH',
                message: 'Directory picker returned an invalid path.',
                statusCode: 500
            );
        }

        return $resolvedPath;
    }

    private function throwForFailure(Process $process): never
    {
        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());
        $combined = strtolower(trim($stderr.' '.$stdout));
        $exitCode = $process->getExitCode() ?? 1;

        if ($this->looksLikeCancellation($combined, $exitCode, $stderr, $stdout)) {
            throw new DirectoryPickerException(
                errorCode: 'DIRECTORY_PICKER_CANCELLED',
                message: 'Folder selection was cancelled.',
                statusCode: 422
            );
        }

        throw new DirectoryPickerException(
            errorCode: 'DIRECTORY_PICKER_FAILED',
            message: $stderr !== '' ? $stderr : 'Failed to open system directory picker.',
            statusCode: 500
        );
    }

    private function looksLikeCancellation(string $combined, int $exitCode, string $stderr, string $stdout): bool
    {
        if (str_contains($combined, 'user canceled') || str_contains($combined, 'user cancelled') || str_contains($combined, '(-128)')) {
            return true;
        }

        if (str_contains($combined, 'cancel') && ! str_contains($combined, 'error')) {
            return true;
        }

        return $exitCode === 1 && trim($stderr) === '' && trim($stdout) === '';
    }

    private function binaryExists(string $binary): bool
    {
        $path = getenv('PATH');
        if (! is_string($path) || $path === '') {
            return false;
        }

        $extensions = [''];
        if (PHP_OS_FAMILY === 'Windows') {
            $extensions = array_merge($extensions, ['.exe', '.bat', '.cmd']);
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory === '') {
                continue;
            }

            $basePath = rtrim($directory, DIRECTORY_SEPARATOR);

            foreach ($extensions as $extension) {
                $candidate = $basePath.DIRECTORY_SEPARATOR.$binary.$extension;
                if (is_file($candidate) && is_executable($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function escapeAppleScript(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function escapePowerShellString(string $value): string
    {
        return str_replace('"', '\"', $value);
    }
}
