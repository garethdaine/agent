<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IntegrityValidator
{
    public function validate(string $extractedPath, ?string $expectedChecksum, string $source): StageResult
    {
        $fileHashes = $this->computeFileHashes($extractedPath);
        $checks = 1;

        // For library installs, verify archive checksum
        if ($source === 'library' && $expectedChecksum !== null) {
            $checks++;
            $overallHash = $this->computeOverallHash($fileHashes);

            if ($overallHash !== $expectedChecksum) {
                return StageResult::fail('integrity', [
                    [
                        'check' => 'checksum_match',
                        'severity' => StageResult::SEVERITY_BLOCK,
                        'message' => "Checksum mismatch. Expected: {$expectedChecksum}, Got: {$overallHash}",
                    ],
                ], $checks, [], ['file_hashes' => $fileHashes]);
            }
        }

        return StageResult::pass('integrity', $checks, ['file_hashes' => $fileHashes]);
    }

    /**
     * @return array<string, string> Relative path => SHA-256 hash
     */
    private function computeFileHashes(string $extractedPath): array
    {
        $hashes = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractedPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace($extractedPath.'/', '', $file->getPathname());
            $hashes[$relativePath] = hash_file('sha256', $file->getPathname());
        }

        ksort($hashes);

        return $hashes;
    }

    private function computeOverallHash(array $fileHashes): string
    {
        $combined = '';
        foreach ($fileHashes as $path => $hash) {
            $combined .= $path.':'.$hash."\n";
        }

        return hash('sha256', $combined);
    }
}
