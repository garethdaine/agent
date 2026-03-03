<?php

declare(strict_types=1);

namespace App\Support\RepoAnalysis\Analyzers;

class CodeQualityStandardsAnalyzer extends AbstractAnalyzer
{
    /**
     * @var array<int, array{tool: string, category: string, pattern: string}>
     */
    private const TOOL_RULES = [
        ['tool' => 'EditorConfig', 'category' => 'formatting', 'pattern' => '/(?:^|\/)\.editorconfig$/i'],
        ['tool' => 'Prettier', 'category' => 'formatting', 'pattern' => '/(?:^|\/)(?:prettier\.config\.(?:js|cjs|mjs|ts)|\.prettierrc(?:\.(?:js|json|yaml|yml))?)$/i'],
        ['tool' => 'ESLint', 'category' => 'linting', 'pattern' => '/(?:^|\/)(?:eslint\.config\.(?:js|cjs|mjs|ts)|\.eslintrc(?:\.(?:js|json|yaml|yml))?)$/i'],
        ['tool' => 'Stylelint', 'category' => 'linting', 'pattern' => '/(?:^|\/)(?:stylelint\.config\.(?:js|cjs|mjs|ts)|\.stylelintrc(?:\.(?:js|json|yaml|yml))?)$/i'],
        ['tool' => 'PHP CS Fixer / PHPCS', 'category' => 'linting', 'pattern' => '/(?:^|\/)(?:\.php-cs-fixer\.php|phpcs\.xml(?:\.dist)?|\.phpcs\.xml)$/i'],
        ['tool' => 'Laravel Pint', 'category' => 'formatting', 'pattern' => '/(?:^|\/)pint\.json$/i'],
        ['tool' => 'PHPStan', 'category' => 'static_analysis', 'pattern' => '/(?:^|\/)phpstan\.neon(?:\.dist)?$/i'],
        ['tool' => 'Psalm', 'category' => 'static_analysis', 'pattern' => '/(?:^|\/)psalm\.xml(?:\.dist)?$/i'],
        ['tool' => 'Mypy', 'category' => 'static_analysis', 'pattern' => '/(?:^|\/)\.mypy\.ini$|(?:^|\/)mypy\.ini$/i'],
        ['tool' => 'Pyright', 'category' => 'static_analysis', 'pattern' => '/(?:^|\/)pyrightconfig\.json$/i'],
        ['tool' => 'Ruff', 'category' => 'linting', 'pattern' => '/(?:^|\/)(?:ruff\.toml|\.ruff\.toml)$/i'],
        ['tool' => 'Flake8', 'category' => 'linting', 'pattern' => '/(?:^|\/)\.flake8$/i'],
        ['tool' => 'GolangCI-Lint', 'category' => 'linting', 'pattern' => '/(?:^|\/)(?:\.golangci\.(?:yml|yaml)|golangci\.(?:yml|yaml))$/i'],
        ['tool' => 'Rustfmt', 'category' => 'formatting', 'pattern' => '/(?:^|\/)rustfmt\.toml$/i'],
        ['tool' => 'Clippy', 'category' => 'linting', 'pattern' => '/(?:^|\/)clippy\.toml$/i'],
        ['tool' => 'Biome', 'category' => 'formatting', 'pattern' => '/(?:^|\/)biome\.jsonc?$/i'],
        ['tool' => 'SwiftLint', 'category' => 'linting', 'pattern' => '/(?:^|\/)\.swiftlint\.(?:yml|yaml)$/i'],
        ['tool' => 'RuboCop', 'category' => 'linting', 'pattern' => '/(?:^|\/)\.rubocop\.yml$/i'],
        ['tool' => 'CI Workflow', 'category' => 'ci', 'pattern' => '/(?:^|\/)\.github\/workflows\/[^\/]+\.(?:yml|yaml)$/i'],
        ['tool' => 'GitLab CI', 'category' => 'ci', 'pattern' => '/(?:^|\/)\.gitlab-ci\.yml$/i'],
        ['tool' => 'CircleCI', 'category' => 'ci', 'pattern' => '/(?:^|\/)\.circleci\/config\.yml$/i'],
        ['tool' => 'Azure Pipelines', 'category' => 'ci', 'pattern' => '/(?:^|\/)azure-pipelines\.yml$/i'],
        ['tool' => 'Jenkins', 'category' => 'ci', 'pattern' => '/(?:^|\/)Jenkinsfile$/i'],
    ];

    public function key(): string
    {
        return 'code_quality_standards';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * @return array<int, string>
     */
    public function dependencies(): array
    {
        return ['dependency_manifest'];
    }

    public function supports(array $snapshot): bool
    {
        return true;
    }

    public function analyze(array $snapshot): array
    {
        $paths = $this->snapshotPaths($snapshot);

        $standardsFiles = $this->discoverStandardsFiles($snapshot, $paths);
        $qualityCommands = $this->discoverQualityCommands($snapshot, $paths);
        $ciPipelines = $this->ciPipelineFiles($standardsFiles);
        $toolingCategories = $this->categoryCounts($standardsFiles);
        $warnings = [];

        if ($standardsFiles === []) {
            $warnings[] = [
                'code' => 'no_quality_configs_discovered',
                'message' => 'No coding standards or quality configuration files were discovered.',
            ];
        }

        if ($ciPipelines === []) {
            $warnings[] = [
                'code' => 'no_ci_quality_pipeline_discovered',
                'message' => 'No CI workflow file was discovered for automated quality checks.',
            ];
        }

        if ($qualityCommands === []) {
            $warnings[] = [
                'code' => 'no_quality_commands_discovered',
                'message' => 'No explicit lint/test/format/static-analysis scripts were discovered in manifests.',
            ];
        }

        $tools = array_values(array_unique(array_map(
            static fn (array $entry): string => (string) ($entry['tool'] ?? ''),
            $standardsFiles
        )));
        sort($tools, SORT_STRING);

        $categoryKeys = array_keys($toolingCategories);
        sort($categoryKeys, SORT_STRING);

        return $this->result([
            'snapshot_hash' => $this->snapshotHash($snapshot),
            'standards_file_count' => count($standardsFiles),
            'standards_files' => $standardsFiles,
            'tool_count' => count($tools),
            'tools' => $tools,
            'tooling_categories' => $toolingCategories,
            'category_keys' => $categoryKeys,
            'quality_command_count' => count($qualityCommands),
            'quality_commands' => $qualityCommands,
            'ci_pipeline_count' => count($ciPipelines),
            'ci_pipelines' => $ciPipelines,
            'quality_signals' => $this->qualitySignals($toolingCategories, $qualityCommands),
        ], $warnings);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, string>  $paths
     * @return array<int, array{tool: string, category: string, file: string}>
     */
    private function discoverStandardsFiles(array $snapshot, array $paths): array
    {
        $records = [];

        foreach ($paths as $path) {
            $normalizedPath = str_replace('\\', '/', $path);

            foreach (self::TOOL_RULES as $rule) {
                if (preg_match($rule['pattern'], $normalizedPath) !== 1) {
                    continue;
                }

                $records[] = [
                    'tool' => $rule['tool'],
                    'category' => $rule['category'],
                    'file' => $normalizedPath,
                ];
            }

            $basename = strtolower(basename($normalizedPath));
            if ($basename === 'pyproject.toml') {
                foreach ($this->detectPyprojectTools($snapshot, $normalizedPath) as $toolRecord) {
                    $records[] = $toolRecord;
                }
            }
        }

        $records = $this->uniqueRecords($records);

        usort($records, static function (array $left, array $right): int {
            $leftFile = (string) ($left['file'] ?? '');
            $rightFile = (string) ($right['file'] ?? '');
            $fileComparison = strcmp($leftFile, $rightFile);

            if ($fileComparison !== 0) {
                return $fileComparison;
            }

            return strcmp((string) ($left['tool'] ?? ''), (string) ($right['tool'] ?? ''));
        });

        return $records;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array{tool: string, category: string, file: string}>
     */
    private function detectPyprojectTools(array $snapshot, string $path): array
    {
        $records = [];

        $content = $this->fileContentByPath($snapshot, $path);
        if (! is_string($content) || trim($content) === '') {
            return [];
        }

        $toolSections = [
            '[tool.black]' => ['tool' => 'Black', 'category' => 'formatting'],
            '[tool.isort]' => ['tool' => 'isort', 'category' => 'formatting'],
            '[tool.ruff]' => ['tool' => 'Ruff', 'category' => 'linting'],
            '[tool.pylint]' => ['tool' => 'Pylint', 'category' => 'linting'],
            '[tool.mypy]' => ['tool' => 'Mypy', 'category' => 'static_analysis'],
            '[tool.pyright]' => ['tool' => 'Pyright', 'category' => 'static_analysis'],
            '[tool.pytest]' => ['tool' => 'Pytest', 'category' => 'testing'],
            '[tool.coverage]' => ['tool' => 'Coverage.py', 'category' => 'testing'],
        ];

        foreach ($toolSections as $marker => $metadata) {
            if (! str_contains($content, $marker)) {
                continue;
            }

            $records[] = [
                'tool' => $metadata['tool'],
                'category' => $metadata['category'],
                'file' => $path,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function discoverQualityCommands(array $snapshot, array $paths): array
    {
        $commands = [];

        foreach ($paths as $path) {
            $normalizedPath = str_replace('\\', '/', $path);
            $basename = strtolower(basename($normalizedPath));

            if ($basename === 'package.json') {
                $content = $this->fileContentByPath($snapshot, $path);
                if (! is_string($content) || trim($content) === '') {
                    continue;
                }

                $decoded = json_decode($content, true);
                if (! is_array($decoded)) {
                    continue;
                }

                $scripts = is_array($decoded['scripts'] ?? null) ? $decoded['scripts'] : [];
                foreach ($scripts as $name => $command) {
                    if (! is_string($name) || ! is_scalar($command)) {
                        continue;
                    }

                    if (! $this->isQualityScriptName($name)) {
                        continue;
                    }

                    $commands[] = sprintf('%s:%s', $normalizedPath, trim((string) $name));
                }

                continue;
            }

            if ($basename === 'composer.json') {
                $content = $this->fileContentByPath($snapshot, $path);
                if (! is_string($content) || trim($content) === '') {
                    continue;
                }

                $decoded = json_decode($content, true);
                if (! is_array($decoded)) {
                    continue;
                }

                $scripts = is_array($decoded['scripts'] ?? null) ? $decoded['scripts'] : [];
                foreach ($scripts as $name => $command) {
                    if (! is_string($name) || (! is_scalar($command) && ! is_array($command))) {
                        continue;
                    }

                    if (! $this->isQualityScriptName($name)) {
                        continue;
                    }

                    $commands[] = sprintf('%s:%s', $normalizedPath, trim((string) $name));
                }

                continue;
            }

            if ($basename === 'makefile') {
                $content = $this->fileContentByPath($snapshot, $path);
                if (! is_string($content) || trim($content) === '') {
                    continue;
                }

                if (preg_match_all('/^([a-zA-Z0-9_.-]+):/m', $content, $matches) !== 1) {
                    continue;
                }

                foreach (($matches[1] ?? []) as $target) {
                    if (! is_string($target) || trim($target) === '') {
                        continue;
                    }

                    if (! $this->isQualityScriptName($target)) {
                        continue;
                    }

                    $commands[] = sprintf('%s:%s', $normalizedPath, trim($target));
                }
            }
        }

        $commands = array_values(array_unique($commands));
        sort($commands, SORT_STRING);

        return array_slice($commands, 0, 120);
    }

    private function isQualityScriptName(string $name): bool
    {
        return preg_match('/(?:^|:|_|-)(lint|test|format|fmt|style|check|quality|qa|typecheck|static|analy[sz]e|phpstan|psalm|pint|ruff|mypy|clippy|coverage)(?:$|:|_|-)/i', $name) === 1;
    }

    /**
     * @param  array<int, array{tool: string, category: string, file: string}>  $standardsFiles
     * @return array<int, string>
     */
    private function ciPipelineFiles(array $standardsFiles): array
    {
        $files = [];

        foreach ($standardsFiles as $record) {
            if (($record['category'] ?? '') !== 'ci') {
                continue;
            }

            $file = trim((string) ($record['file'] ?? ''));
            if ($file === '') {
                continue;
            }

            $files[] = $file;
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param  array<int, array{tool: string, category: string, file: string}>  $standardsFiles
     * @return array<string, int>
     */
    private function categoryCounts(array $standardsFiles): array
    {
        $counts = [];

        foreach ($standardsFiles as $record) {
            $category = trim((string) ($record['category'] ?? ''));
            if ($category === '') {
                continue;
            }

            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param  array<string, int>  $toolingCategories
     * @param  array<int, string>  $qualityCommands
     * @return array<int, string>
     */
    private function qualitySignals(array $toolingCategories, array $qualityCommands): array
    {
        $signals = [];

        if (($toolingCategories['formatting'] ?? 0) > 0) {
            $signals[] = 'Formatting standards configuration detected.';
        }

        if (($toolingCategories['linting'] ?? 0) > 0) {
            $signals[] = 'Linting standards configuration detected.';
        }

        if (($toolingCategories['static_analysis'] ?? 0) > 0) {
            $signals[] = 'Static analysis configuration detected.';
        }

        if (($toolingCategories['testing'] ?? 0) > 0) {
            $signals[] = 'Test tooling configuration detected.';
        }

        if (($toolingCategories['ci'] ?? 0) > 0) {
            $signals[] = 'Automated CI workflow for quality gates detected.';
        }

        if ($qualityCommands !== []) {
            $signals[] = 'Manifest-defined quality commands are available.';
        }

        return $signals;
    }

    /**
     * @param  array<int, array{tool: string, category: string, file: string}>  $records
     * @return array<int, array{tool: string, category: string, file: string}>
     */
    private function uniqueRecords(array $records): array
    {
        $map = [];

        foreach ($records as $record) {
            $tool = trim((string) ($record['tool'] ?? ''));
            $category = trim((string) ($record['category'] ?? ''));
            $file = str_replace('\\', '/', trim((string) ($record['file'] ?? '')));

            if ($tool === '' || $category === '' || $file === '') {
                continue;
            }

            $map[strtolower($tool.'|'.$category.'|'.$file)] = [
                'tool' => $tool,
                'category' => $category,
                'file' => $file,
            ];
        }

        return array_values($map);
    }
}
