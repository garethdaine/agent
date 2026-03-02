<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis;

use App\Models\RepoAnalysisReport;
use App\Models\RepoAnalysisSession;
use Illuminate\Support\Str;

class ExportService
{
    /**
     * @return array{markdown_export_path: string, json_export_path: string}
     */
    public function export(RepoAnalysisSession $session, RepoAnalysisReport $report): array
    {
        $relativeDirectory = $this->normalizedRelativeDirectory(
            (string) config('repo_analysis.exports.relative_directory', 'docs/discovery/repo-analysis')
        );
        $projectRoot = $this->resolvedProjectRoot((string) $session->project_directory);
        $directory = $projectRoot.'/'.$relativeDirectory;

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create export directory: %s', $directory));
        }

        [$markdownPath, $jsonPath] = $this->nextAvailablePair($directory, $this->sessionSlug($session));

        $payload = is_array($report->payload_json) ? $report->payload_json : [];

        file_put_contents($markdownPath, $this->markdown($session, $report, $payload));

        $encodedJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedJson === false) {
            throw new \RuntimeException('Unable to encode report payload as JSON.');
        }

        file_put_contents($jsonPath, $encodedJson.PHP_EOL);

        $report->markdown_export_path = $markdownPath;
        $report->json_export_path = $jsonPath;
        $report->save();

        return [
            'markdown_export_path' => $markdownPath,
            'json_export_path' => $jsonPath,
        ];
    }

    private function resolvedProjectRoot(string $projectDirectory): string
    {
        if ($projectDirectory === '' || ! str_starts_with($projectDirectory, '/')) {
            throw new \InvalidArgumentException('Export path policy violation: project directory must be an absolute path.');
        }

        $resolved = realpath($projectDirectory);
        if ($resolved === false || ! is_dir($resolved)) {
            throw new \InvalidArgumentException('Export path policy violation: project directory could not be resolved.');
        }

        return rtrim($resolved, '/');
    }

    private function normalizedRelativeDirectory(string $relativeDirectory): string
    {
        $normalized = str_replace('\\', '/', trim($relativeDirectory));
        $normalized = trim($normalized, '/');

        if ($normalized === '' || str_starts_with($normalized, '/')) {
            throw new \InvalidArgumentException('Export path policy violation: export directory must be a relative path.');
        }

        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Export path policy violation: traversal segments are not allowed.');
            }
        }

        return $normalized;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function nextAvailablePair(string $directory, string $slug): array
    {
        for ($version = 1; $version <= 200; $version++) {
            $suffix = $version === 1 ? '' : '-v'.$version;
            $markdownPath = sprintf('%s/%s%s.md', $directory, $slug, $suffix);
            $jsonPath = sprintf('%s/%s%s.json', $directory, $slug, $suffix);

            if (! file_exists($markdownPath) && ! file_exists($jsonPath)) {
                return [$markdownPath, $jsonPath];
            }
        }

        $timestamp = time();

        return [
            sprintf('%s/%s-%s.md', $directory, $slug, $timestamp),
            sprintf('%s/%s-%s.json', $directory, $slug, $timestamp),
        ];
    }

    private function sessionSlug(RepoAnalysisSession $session): string
    {
        $base = trim((string) $session->name);
        if ($base === '') {
            $base = 'repo-analysis-session-'.$session->id;
        }

        $slug = Str::of($base)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();

        return $slug !== '' ? $slug : 'repo-analysis-session-'.$session->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markdown(RepoAnalysisSession $session, RepoAnalysisReport $report, array $payload): string
    {
        $lines = [
            '# Repo Analysis Report',
            '',
            'Session: '.$session->id,
            'Report hash: '.(string) $report->report_hash,
            'Generated at: '.(string) data_get($payload, 'generated_at', ''),
            '',
        ];

        $artifacts = data_get($payload, 'artifacts', []);
        if (is_array($artifacts) && $artifacts !== []) {
            $lines[] = '## Artifacts';
            $lines[] = '';

            foreach ($artifacts as $artifact) {
                if (! is_array($artifact)) {
                    continue;
                }

                $artifactKey = (string) ($artifact['artifact_key'] ?? '');
                $artifactType = (string) ($artifact['artifact_type'] ?? '');
                $contentHash = (string) ($artifact['content_hash'] ?? '');
                $lines[] = sprintf('- `%s` (%s) `%s`', $artifactKey, $artifactType, $contentHash);
            }

            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }
}

