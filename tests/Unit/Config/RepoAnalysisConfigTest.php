<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * Assumptions:
 * - Redis and Horizon are available.
 * - Repo Analysis is disabled by default unless explicitly enabled.
 */
class RepoAnalysisConfigTest extends TestCase
{
    public function test_repo_analysis_is_disabled_by_default(): void
    {
        $this->assertFalse(config('repo_analysis.enabled'));
    }

    public function test_repo_analysis_user_defaults_are_safe_and_deterministic(): void
    {
        $this->assertSame(2, config('repo_analysis.user.max_active_sessions_per_user'));
        $this->assertFalse(config('repo_analysis.user.narrative_synthesis_default'));
    }

    public function test_mandatory_excludes_include_safety_paths_and_are_not_empty(): void
    {
        $mandatoryExcludes = config('repo_analysis.scan.mandatory_excludes');
        $excludePaths = config('repo_analysis.scan.exclude_paths');

        $this->assertIsArray($mandatoryExcludes);
        $this->assertNotEmpty($mandatoryExcludes);
        $this->assertIsArray($excludePaths);

        foreach (['vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/', '.git/'] as $path) {
            $this->assertContains($path, $mandatoryExcludes);
            $this->assertContains($path, $excludePaths);
        }
    }

    public function test_retention_policy_fields_are_present(): void
    {
        $retention = config('repo_analysis.retention');

        $this->assertIsArray($retention);
        $this->assertArrayHasKey('task_artifacts_ttl_days', $retention);
        $this->assertArrayHasKey('final_reports_retention', $retention);
        $this->assertSame(30, $retention['task_artifacts_ttl_days']);
        $this->assertSame('indefinite', $retention['final_reports_retention']);
    }

    public function test_missing_and_invalid_overrides_fallback_to_safe_defaults(): void
    {
        $missingOverridesConfig = $this->loadConfigWithEnvOverrides([]);
        $invalidOverridesConfig = $this->loadConfigWithEnvOverrides([
            'REPO_ANALYSIS_MAX_ACTIVE_SESSIONS_PER_USER' => '-1',
            'REPO_ANALYSIS_NARRATIVE_SYNTHESIS_DEFAULT' => 'maybe',
            'REPO_ANALYSIS_EXCLUDE_PATHS' => ' , , ',
        ]);

        $this->assertSame(2, $missingOverridesConfig['user']['max_active_sessions_per_user']);
        $this->assertFalse($missingOverridesConfig['user']['narrative_synthesis_default']);
        $this->assertSame(2, $invalidOverridesConfig['user']['max_active_sessions_per_user']);
        $this->assertFalse($invalidOverridesConfig['user']['narrative_synthesis_default']);

        foreach (['vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/', '.git/'] as $path) {
            $this->assertContains($path, $invalidOverridesConfig['scan']['mandatory_excludes']);
            $this->assertContains($path, $invalidOverridesConfig['scan']['exclude_paths']);
        }
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, mixed>
     */
    private function loadConfigWithEnvOverrides(array $overrides): array
    {
        $trackedKeys = [
            'REPO_ANALYSIS_ENABLED',
            'REPO_ANALYSIS_MAX_ACTIVE_SESSIONS_PER_USER',
            'REPO_ANALYSIS_NARRATIVE_SYNTHESIS_DEFAULT',
            'REPO_ANALYSIS_EXCLUDE_PATHS',
        ];

        $originalValues = [];

        foreach ($trackedKeys as $key) {
            $originalValues[$key] = getenv($key);

            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        foreach ($overrides as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            /** @var array<string, mixed> $config */
            $config = require base_path('config/repo_analysis.php');

            return $config;
        } finally {
            foreach ($trackedKeys as $key) {
                $original = $originalValues[$key];

                if ($original === false) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);
                    continue;
                }

                putenv(sprintf('%s=%s', $key, $original));
                $_ENV[$key] = $original;
                $_SERVER[$key] = $original;
            }
        }
    }
}
