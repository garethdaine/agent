<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation;

final readonly class SkillValidationResult
{
    /**
     * @param  array<string, StageResult>  $stages
     * @param  array<int, string>  $warnings
     * @param  array<int, array{check: string, severity: string, message: string}>  $blockingErrors
     * @param  array<string, string>  $fileHashes
     */
    public function __construct(
        public bool $overallPass,
        public float $riskScore,
        public array $stages,
        public array $warnings = [],
        public array $blockingErrors = [],
        public array $fileHashes = [],
    ) {}

    public function toArray(): array
    {
        $stages = [];
        foreach ($this->stages as $name => $stage) {
            $stages[$name] = [
                'passed' => $stage->passed,
                'checks' => $stage->checks,
                'failures' => $stage->failures,
                'warnings' => $stage->warnings,
                'details' => $stage->details,
            ];
        }

        return [
            'overall_pass' => $this->overallPass,
            'risk_score' => $this->riskScore,
            'stages' => $stages,
            'warnings' => $this->warnings,
            'blocking_errors' => $this->blockingErrors,
            'file_hashes' => $this->fileHashes,
        ];
    }
}
