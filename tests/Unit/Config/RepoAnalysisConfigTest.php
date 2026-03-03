<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * Assumptions:
 * - Redis and Horizon are available.
 * - Code Analysis is disabled by default unless explicitly enabled.
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

    public function test_ai_defaults_are_defined(): void
    {
        $ai = config('repo_analysis.ai');

        $this->assertIsArray($ai);
        $this->assertArrayHasKey('enabled', $ai);
        $this->assertArrayHasKey('task_timeout_seconds', $ai);
        $this->assertArrayHasKey('queue_timeout_buffer_seconds', $ai);
        $this->assertArrayHasKey('max_stream_message_length', $ai);
    }

    public function test_queue_supervisor_timeout_defaults_include_ai_timeout_buffer(): void
    {
        $aiTimeout = (int) config('repo_analysis.ai.task_timeout_seconds');
        $buffer = (int) config('repo_analysis.ai.queue_timeout_buffer_seconds');
        $queueTimeout = (int) config('repo_analysis.queue.supervisor.timeout_seconds');

        $this->assertSame(3600, $aiTimeout);
        $this->assertSame(180, $buffer);
        $this->assertSame($aiTimeout + $buffer, $queueTimeout);
        $this->assertGreaterThan($aiTimeout, $queueTimeout);
    }

    public function test_default_coverage_requirements_include_pattern_and_quality_artifacts(): void
    {
        $required = config('repo_analysis.coverage.required_artifact_classes');

        $this->assertIsArray($required);
        $this->assertContains('filesystem_manifest', $required);
        $this->assertContains('dependency_manifest', $required);
        $this->assertContains('architecture_patterns', $required);
        $this->assertContains('test_coverage_map', $required);
        $this->assertContains('risk_hotspot', $required);
        $this->assertContains('code_quality_standards', $required);
    }

    public function test_missing_and_invalid_overrides_fallback_to_safe_defaults(): void
    {
        $missingOverridesConfig = $this->loadConfigWithEnvOverrides([]);
        $invalidOverridesConfig = $this->loadConfigWithEnvOverrides([
            'REPO_ANALYSIS_MAX_ACTIVE_SESSIONS_PER_USER' => '-1',
            'REPO_ANALYSIS_NARRATIVE_SYNTHESIS_DEFAULT' => 'maybe',
            'REPO_ANALYSIS_EXCLUDE_PATHS' => ' , , ',
            'REPO_ANALYSIS_AI_ENABLED' => 'not-a-bool',
            'REPO_ANALYSIS_AI_TASK_TIMEOUT_SECONDS' => '999999',
            'REPO_ANALYSIS_QUEUE_TIMEOUT_BUFFER_SECONDS' => '999999',
            'REPO_ANALYSIS_AI_MAX_STREAM_MESSAGE_LENGTH' => '1',
        ]);

        $this->assertSame(2, $missingOverridesConfig['user']['max_active_sessions_per_user']);
        $this->assertFalse($missingOverridesConfig['user']['narrative_synthesis_default']);
        $this->assertSame(2, $invalidOverridesConfig['user']['max_active_sessions_per_user']);
        $this->assertFalse($invalidOverridesConfig['user']['narrative_synthesis_default']);
        $this->assertTrue($invalidOverridesConfig['ai']['enabled']);
        $this->assertSame(3600, $invalidOverridesConfig['ai']['task_timeout_seconds']);
        $this->assertSame(180, $invalidOverridesConfig['ai']['queue_timeout_buffer_seconds']);
        $this->assertSame(3780, $invalidOverridesConfig['queue']['supervisor']['timeout_seconds']);
        $this->assertSame(320, $invalidOverridesConfig['ai']['max_stream_message_length']);

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
            'REPO_ANALYSIS_AI_ENABLED',
            'REPO_ANALYSIS_AI_TASK_TIMEOUT_SECONDS',
            'REPO_ANALYSIS_QUEUE_TIMEOUT_BUFFER_SECONDS',
            'REPO_ANALYSIS_AI_MAX_STREAM_MESSAGE_LENGTH',
            'HORIZON_REPO_ANALYSIS_TIMEOUT',
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
