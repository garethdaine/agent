<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Services\Skills\DTOs\SkillParseResult;
use App\Services\Skills\Validation\CodeSafetyValidator;
use App\Services\Skills\Validation\ContentAnalysisValidator;
use App\Services\Skills\Validation\FormatValidator;
use App\Services\Skills\Validation\IntegrityValidator;
use App\Services\Skills\Validation\SchemaValidator;
use App\Services\Skills\Validation\SkillValidationResult;
use App\Services\Skills\Validation\StageResult;

final class SkillValidator
{
    public function __construct(
        private readonly FormatValidator $formatValidator,
        private readonly SchemaValidator $schemaValidator,
        private readonly ContentAnalysisValidator $contentAnalysisValidator,
        private readonly CodeSafetyValidator $codeSafetyValidator,
        private readonly IntegrityValidator $integrityValidator,
    ) {}

    public function validate(
        string $extractedPath,
        SkillParseResult $parsed,
        string $source,
        int $teamId,
        ?string $expectedChecksum = null,
    ): SkillValidationResult {
        $stages = [];
        $warnings = [];
        $blockingErrors = [];
        $fileHashes = [];
        $riskScore = 0.0;

        // Stage 1: Format
        $formatResult = $this->formatValidator->validate($extractedPath);
        $stages['format'] = $formatResult;
        $this->collectWarnings($formatResult, $warnings);

        if ($formatResult->hasBlockingFailures()) {
            return $this->buildResult(false, $riskScore, $stages, $warnings, $formatResult->failures, $fileHashes);
        }

        // Stage 2: Schema
        $schemaResult = $this->schemaValidator->validate($parsed, $teamId);
        $stages['schema'] = $schemaResult;
        $this->collectWarnings($schemaResult, $warnings);

        if ($schemaResult->hasBlockingFailures()) {
            return $this->buildResult(false, $riskScore, $stages, $warnings, $schemaResult->failures, $fileHashes);
        }

        // Stage 3: Content Analysis
        $skillContent = file_get_contents($extractedPath.'/SKILL.md') ?: '';
        $contentResult = $this->contentAnalysisValidator->validate($skillContent, $extractedPath, $parsed->riskLevel);
        $stages['content_analysis'] = $contentResult;
        $this->collectWarnings($contentResult, $warnings);
        $riskScore = $contentResult->details['weighted_score'] ?? 0.0;

        if ($contentResult->hasBlockingFailures()) {
            return $this->buildResult(false, $riskScore, $stages, $warnings, $contentResult->failures, $fileHashes);
        }

        // Stage 4: Code Safety
        $codeSafetyResult = $this->codeSafetyValidator->validate($extractedPath);
        $stages['code_safety'] = $codeSafetyResult;
        $this->collectWarnings($codeSafetyResult, $warnings);

        if ($codeSafetyResult->hasBlockingFailures()) {
            return $this->buildResult(false, $riskScore, $stages, $warnings, $codeSafetyResult->failures, $fileHashes);
        }

        // Stage 5: Integrity
        $integrityResult = $this->integrityValidator->validate($extractedPath, $expectedChecksum, $source);
        $stages['integrity'] = $integrityResult;
        $this->collectWarnings($integrityResult, $warnings);
        $fileHashes = $integrityResult->details['file_hashes'] ?? [];

        if ($integrityResult->hasBlockingFailures()) {
            return $this->buildResult(false, $riskScore, $stages, $warnings, $integrityResult->failures, $fileHashes);
        }

        // Collect all blocking errors across stages
        foreach ($stages as $stage) {
            foreach ($stage->failures as $failure) {
                if ($failure['severity'] === StageResult::SEVERITY_BLOCK) {
                    $blockingErrors[] = $failure;
                }
            }
        }

        $overallPass = empty($blockingErrors);

        return $this->buildResult($overallPass, $riskScore, $stages, $warnings, $blockingErrors, $fileHashes);
    }

    private function collectWarnings(StageResult $result, array &$warnings): void
    {
        foreach ($result->warnings as $warning) {
            $warnings[] = $warning;
        }
    }

    private function buildResult(
        bool $overallPass,
        float $riskScore,
        array $stages,
        array $warnings,
        array $blockingErrors,
        array $fileHashes,
    ): SkillValidationResult {
        return new SkillValidationResult(
            overallPass: $overallPass,
            riskScore: $riskScore,
            stages: $stages,
            warnings: $warnings,
            blockingErrors: $blockingErrors,
            fileHashes: $fileHashes,
        );
    }
}
