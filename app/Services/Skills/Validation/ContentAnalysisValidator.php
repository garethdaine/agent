<?php

declare(strict_types=1);

namespace App\Services\Skills\Validation;

use App\Services\Skills\Validation\Analyzers\AuthorityEscalationAnalyzer;
use App\Services\Skills\Validation\Analyzers\BoundaryAnalyzer;
use App\Services\Skills\Validation\Analyzers\ExfiltrationAnalyzer;
use App\Services\Skills\Validation\Analyzers\LlmReviewAnalyzer;
use App\Services\Skills\Validation\Analyzers\PatternMatchAnalyzer;

class ContentAnalysisValidator
{
    public function __construct(
        private readonly PatternMatchAnalyzer $patternMatch,
        private readonly BoundaryAnalyzer $boundary,
        private readonly AuthorityEscalationAnalyzer $authority,
        private readonly ExfiltrationAnalyzer $exfiltration,
        private readonly LlmReviewAnalyzer $llmReview,
    ) {}

    public function validate(string $content, string $extractedPath, string $riskLevel): StageResult
    {
        $warnings = [];

        // Run all 4 base analyzers
        $patternScore = $this->patternMatch->analyze($content);
        $boundaryScore = $this->boundary->analyze($content);
        $authorityScore = $this->authority->analyze($content);
        $exfiltrationScore = $this->exfiltration->analyze($content, $extractedPath);

        // Determine if LLM review needed and run it
        $llmScore = $this->llmReview->analyze($content, $riskLevel);

        // Select weight set based on LLM availability
        if ($llmScore !== null) {
            $weights = config('agent.skills.validation.weights_with_llm');
            $weightedScore = ($patternScore * $weights['pattern_match'])
                + ($boundaryScore * $weights['boundary_analysis'])
                + ($authorityScore * $weights['authority_escalation'])
                + ($exfiltrationScore * $weights['exfiltration'])
                + ($llmScore * $weights['llm_review']);
        } else {
            $llmReviewEnabled = (bool) config('agent.features.skills.validation.llm_review', false);
            $isElevatedRisk = in_array($riskLevel, ['elevated', 'critical'], true);

            if ($llmReviewEnabled || $isElevatedRisk) {
                $warnings[] = 'LLM review was requested but unavailable. Using 4-strategy weights.';
            }

            $weights = config('agent.skills.validation.weights_without_llm');
            $weightedScore = ($patternScore * $weights['pattern_match'])
                + ($boundaryScore * $weights['boundary_analysis'])
                + ($authorityScore * $weights['authority_escalation'])
                + ($exfiltrationScore * $weights['exfiltration']);
        }

        $thresholds = config('agent.skills.validation.thresholds');
        $classification = $this->classify($weightedScore, $thresholds);

        $details = [
            'scores' => [
                'pattern_match' => round($patternScore, 4),
                'boundary_analysis' => round($boundaryScore, 4),
                'authority_escalation' => round($authorityScore, 4),
                'exfiltration' => round($exfiltrationScore, 4),
                'llm_review' => $llmScore !== null ? round($llmScore, 4) : null,
            ],
            'weighted_score' => round($weightedScore, 4),
            'classification' => $classification,
            'used_llm' => $llmScore !== null,
        ];

        if ($classification === 'block') {
            return StageResult::fail('content_analysis', [
                [
                    'check' => 'content_analysis_score',
                    'severity' => StageResult::SEVERITY_BLOCK,
                    'message' => "Content analysis score {$weightedScore} exceeds block threshold.",
                ],
            ], 4 + ($llmScore !== null ? 1 : 0), $warnings, $details);
        }

        if ($classification === 'admin_confirm') {
            $warnings[] = "Content analysis score {$weightedScore} requires admin confirmation.";
        }

        if ($classification === 'warn') {
            $warnings[] = "Content analysis detected potential issues (score: {$weightedScore}).";
        }

        $checks = 4 + ($llmScore !== null ? 1 : 0);

        if (! empty($warnings)) {
            return StageResult::warn('content_analysis', $warnings, $checks, $details);
        }

        return StageResult::pass('content_analysis', $checks, $details);
    }

    private function classify(float $score, array $thresholds): string
    {
        if ($score >= $thresholds['admin_confirm']) {
            return 'block';
        }
        if ($score >= $thresholds['warn']) {
            return 'admin_confirm';
        }
        if ($score >= $thresholds['clean']) {
            return 'warn';
        }

        return 'clean';
    }
}
