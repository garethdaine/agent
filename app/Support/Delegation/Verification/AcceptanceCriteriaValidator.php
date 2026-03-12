<?php

declare(strict_types=1);

namespace App\Support\Delegation\Verification;

use App\Support\Delegation\Verification\DTOs\AcceptanceCriteriaResult;
use App\Support\Delegation\Verification\DTOs\CriterionResult;
use Illuminate\Support\Facades\Process;

/**
 * Validates machine-verifiable acceptance criteria defined in task contracts.
 *
 * Replaces subjective AI agreement with deterministic checks, addressing
 * the Berdoz et al. (2026) finding that verifiable task completion is
 * more reliable than multi-agent consensus.
 *
 * Supported criterion types:
 * - file_exists: Check that a file exists at a given path
 * - schema_validates: Validate a JSON file against a JSON schema
 * - command_succeeds: Run a shell command and check exit code is 0
 * - output_contains: Check a JSON file contains a specific key/value pair
 */
class AcceptanceCriteriaValidator
{
    /**
     * Validate all acceptance criteria against the task working directory.
     *
     * @param  array<int, array<string, mixed>>  $criteria  Acceptance criteria from contract
     * @param  string  $workingDirectory  The directory to resolve relative paths against
     */
    public function validate(array $criteria, string $workingDirectory): AcceptanceCriteriaResult
    {
        $results = [];
        $allPassed = true;

        foreach ($criteria as $criterion) {
            $result = $this->validateSingleCriterion($criterion, $workingDirectory);
            $results[] = $result;

            if (! $result->passed) {
                $allPassed = false;
            }
        }

        return new AcceptanceCriteriaResult($allPassed, $results);
    }

    private function validateSingleCriterion(array $criterion, string $workingDirectory): CriterionResult
    {
        $type = $criterion['type'] ?? 'unknown';
        $description = $criterion['description'] ?? "Check: {$type}";

        return match ($type) {
            'file_exists' => $this->checkFileExists($criterion, $workingDirectory, $description),
            'schema_validates' => $this->checkSchemaValidates($criterion, $workingDirectory, $description),
            'command_succeeds' => $this->checkCommandSucceeds($criterion, $workingDirectory, $description),
            'output_contains' => $this->checkOutputContains($criterion, $workingDirectory, $description),
            default => CriterionResult::failed("Unknown criterion type: {$type}", $type, $description),
        };
    }

    private function checkFileExists(array $criterion, string $wd, string $description): CriterionResult
    {
        $path = $this->resolvePath($criterion['path'] ?? '', $wd);

        if (file_exists($path)) {
            return CriterionResult::passed('file_exists', $description);
        }

        return CriterionResult::failed(
            "File not found: {$criterion['path']}",
            'file_exists',
            $description
        );
    }

    private function checkSchemaValidates(array $criterion, string $wd, string $description): CriterionResult
    {
        $path = $this->resolvePath($criterion['path'] ?? '', $wd);

        if (! file_exists($path)) {
            return CriterionResult::failed(
                "File not found for schema validation: {$criterion['path']}",
                'schema_validates',
                $description
            );
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return CriterionResult::failed(
                "Could not read file: {$criterion['path']}",
                'schema_validates',
                $description
            );
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return CriterionResult::failed(
                'File is not valid JSON: '.json_last_error_msg(),
                'schema_validates',
                $description
            );
        }

        $schema = $criterion['schema'] ?? [];

        // Validate required fields from schema
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (! array_key_exists($requiredField, $data)) {
                    return CriterionResult::failed(
                        "Missing required field: {$requiredField}",
                        'schema_validates',
                        $description
                    );
                }
            }
        }

        // Validate property types from schema
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $prop => $propSchema) {
                if (! array_key_exists($prop, $data)) {
                    continue; // Only validate present properties (required check above handles presence)
                }

                if (isset($propSchema['type'])) {
                    $valid = match ($propSchema['type']) {
                        'string' => is_string($data[$prop]),
                        'integer' => is_int($data[$prop]),
                        'number' => is_numeric($data[$prop]),
                        'boolean' => is_bool($data[$prop]),
                        'array' => is_array($data[$prop]) && array_is_list($data[$prop]),
                        'object' => is_array($data[$prop]) && ! array_is_list($data[$prop]),
                        default => true,
                    };

                    if (! $valid) {
                        return CriterionResult::failed(
                            "Property '{$prop}' has wrong type: expected {$propSchema['type']}",
                            'schema_validates',
                            $description
                        );
                    }
                }
            }
        }

        return CriterionResult::passed('schema_validates', $description);
    }

    private function checkCommandSucceeds(array $criterion, string $wd, string $description): CriterionResult
    {
        $command = $criterion['command'] ?? '';

        if (empty($command)) {
            return CriterionResult::failed(
                'No command specified',
                'command_succeeds',
                $description
            );
        }

        $timeout = (int) config('delegation.verification_timeout_seconds', 300);

        $result = Process::path($wd)
            ->timeout($timeout)
            ->run($command);

        if ($result->exitCode() === 0) {
            return CriterionResult::passed('command_succeeds', $description);
        }

        return CriterionResult::failed(
            "Command failed (exit {$result->exitCode()}): ".mb_substr($result->errorOutput(), 0, 500),
            'command_succeeds',
            $description
        );
    }

    private function checkOutputContains(array $criterion, string $wd, string $description): CriterionResult
    {
        $path = $this->resolvePath($criterion['path'] ?? '', $wd);

        if (! file_exists($path)) {
            return CriterionResult::failed(
                "File not found: {$criterion['path']}",
                'output_contains',
                $description
            );
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return CriterionResult::failed(
                "Could not read file: {$criterion['path']}",
                'output_contains',
                $description
            );
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return CriterionResult::failed(
                'File is not valid JSON: '.json_last_error_msg(),
                'output_contains',
                $description
            );
        }

        $key = $criterion['key'] ?? '';
        $expectedValue = $criterion['value'] ?? null;

        // Support dot-notation for nested keys
        $actual = data_get($data, $key);

        if ($actual === null && ! array_key_exists($key, $data)) {
            return CriterionResult::failed(
                "Key '{$key}' not found in output",
                'output_contains',
                $description
            );
        }

        // Compare as strings for consistency
        if ((string) $actual !== (string) $expectedValue) {
            return CriterionResult::failed(
                "Key '{$key}' has value '".((string) $actual)."', expected '{$expectedValue}'",
                'output_contains',
                $description
            );
        }

        return CriterionResult::passed('output_contains', $description);
    }

    /**
     * Resolve a potentially relative path against the working directory.
     */
    private function resolvePath(string $path, string $workingDirectory): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($workingDirectory, '/').'/'.ltrim($path, '/');
    }
}
