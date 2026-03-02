<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

interface AnalyzerInterface
{
    public function key(): string;

    public function version(): string;

    /**
     * @return array<int, string>
     */
    public function dependencies(): array;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function supports(array $snapshot): bool;

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *   analyzer_key: string,
     *   analyzer_version: string,
     *   artifact_type: string,
     *   artifact_key: string,
     *   payload: array<string, mixed>,
     *   warnings: array<int, array<string, mixed>>,
     *   warning_artifact_path: string|null,
     *   output_hash: string
     * }
     */
    public function analyze(array $snapshot): array;
}
