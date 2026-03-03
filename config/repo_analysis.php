<?php

$defaultMaxActiveSessionsPerUser = 2;
$defaultTaskArtifactsTtlDays = 30;
$defaultAiTaskTimeoutSeconds = 3600;
$defaultQueueTimeoutBufferSeconds = 180;
$maxAiTaskTimeoutSeconds = 21600;

$mandatoryExcludes = [
    'vendor/',
    'node_modules/',
    'storage/',
    'bootstrap/cache/',
    '.git/',
];

$normalizeDirectoryPath = static function (string $path): string {
    $trimmed = trim($path);

    if ($trimmed === '') {
        return '';
    }

    $normalized = str_replace('\\\\', '/', $trimmed);

    return rtrim($normalized, '/').'/';
};

$coerceBoolean = static function (mixed $value, bool $default): bool {
    if (is_bool($value)) {
        return $value;
    }

    $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    return $filtered ?? $default;
};

$coerceBoundedInt = static function (mixed $value, int $default, int $min, int $max): int {
    if ($value === null || $value === '') {
        return $default;
    }

    if (! is_numeric($value)) {
        return $default;
    }

    $candidate = (int) $value;

    if ($candidate < $min || $candidate > $max) {
        return $default;
    }

    return $candidate;
};

$parseExcludeOverrides = static function (mixed $value) use ($normalizeDirectoryPath): array {
    if (! is_string($value)) {
        return [];
    }

    $parts = array_map('trim', explode(',', $value));
    $parts = array_map($normalizeDirectoryPath, $parts);

    return array_values(array_filter($parts, static fn (string $path): bool => $path !== ''));
};

$excludeOverrides = $parseExcludeOverrides(env('REPO_ANALYSIS_EXCLUDE_PATHS'));
$excludePaths = array_values(array_unique(array_merge($mandatoryExcludes, $excludeOverrides)));

if ($excludePaths === []) {
    $excludePaths = $mandatoryExcludes;
}

$aiTaskTimeoutSeconds = $coerceBoundedInt(
    env('REPO_ANALYSIS_AI_TASK_TIMEOUT_SECONDS', $defaultAiTaskTimeoutSeconds),
    $defaultAiTaskTimeoutSeconds,
    60,
    $maxAiTaskTimeoutSeconds,
);

$queueTimeoutBufferSeconds = $coerceBoundedInt(
    env('REPO_ANALYSIS_QUEUE_TIMEOUT_BUFFER_SECONDS', $defaultQueueTimeoutBufferSeconds),
    $defaultQueueTimeoutBufferSeconds,
    30,
    3600,
);

$defaultSupervisorTimeoutSeconds = min(
    $maxAiTaskTimeoutSeconds,
    max($aiTaskTimeoutSeconds + $queueTimeoutBufferSeconds, $aiTaskTimeoutSeconds + 30),
);

return [
    'enabled' => $coerceBoolean(env('REPO_ANALYSIS_ENABLED', false), false),

    'queue' => [
        'connection' => 'redis',
        'name' => 'code-analysis',
        'supervisor' => [
            'max_processes' => $coerceBoundedInt(env('HORIZON_REPO_ANALYSIS_MAX_PROCESSES', 2), 2, 1, 4),
            'memory_mb' => 128,
            'tries' => 1,
            'backoff_seconds' => 0,
            'timeout_seconds' => $coerceBoundedInt(
                env('HORIZON_REPO_ANALYSIS_TIMEOUT', $defaultSupervisorTimeoutSeconds),
                $defaultSupervisorTimeoutSeconds,
                min($maxAiTaskTimeoutSeconds, $aiTaskTimeoutSeconds + 30),
                $maxAiTaskTimeoutSeconds,
            ),
        ],
    ],

    'user' => [
        'max_active_sessions_per_user' => $coerceBoundedInt(
            env('REPO_ANALYSIS_MAX_ACTIVE_SESSIONS_PER_USER', $defaultMaxActiveSessionsPerUser),
            $defaultMaxActiveSessionsPerUser,
            1,
            10,
        ),
        'narrative_synthesis_default' => $coerceBoolean(
            env('REPO_ANALYSIS_NARRATIVE_SYNTHESIS_DEFAULT', false),
            false,
        ),
    ],

    'scan' => [
        'mandatory_excludes' => $mandatoryExcludes,
        'exclude_paths' => $excludePaths,
    ],

    'coverage' => [
        'required_artifact_classes' => [
            'filesystem_manifest',
            'dependency_manifest',
            'architecture_patterns',
            'risk_hotspot',
            'test_coverage_map',
            'code_quality_standards',
        ],
    ],

    'ai' => [
        'enabled' => $coerceBoolean(env('REPO_ANALYSIS_AI_ENABLED', true), true),
        'task_timeout_seconds' => $aiTaskTimeoutSeconds,
        'queue_timeout_buffer_seconds' => $queueTimeoutBufferSeconds,
        'max_stream_message_length' => $coerceBoundedInt(env('REPO_ANALYSIS_AI_MAX_STREAM_MESSAGE_LENGTH', 320), 320, 80, 4000),
    ],

    'exports' => [
        'relative_directory' => env('REPO_ANALYSIS_EXPORT_RELATIVE_DIRECTORY', 'docs/discovery/code-analysis'),
    ],

    'retention' => [
        'task_artifacts_ttl_days' => $coerceBoundedInt(
            env('REPO_ANALYSIS_TASK_ARTIFACTS_TTL_DAYS', $defaultTaskArtifactsTtlDays),
            $defaultTaskArtifactsTtlDays,
            1,
            3650,
        ),
        'final_reports_retention' => env('REPO_ANALYSIS_FINAL_REPORTS_RETENTION', 'indefinite'),
    ],
];
