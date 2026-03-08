<?php

declare(strict_types=1);

namespace App\Support\Interrogation;

use App\DTOs\Security\InjectionDetectionResult;
use App\Enums\Security\InjectionAction;
use App\Services\Security\ContentSanitizer;
use App\Services\Security\InjectionDetectionEngine;
use App\Services\Security\SecurityConfigProvider;
use App\Support\Security\TokenEstimator;

class PromptIsolationService
{
    private const int FINDING_MAX_LENGTH = 220;

    public function __construct(
        private readonly InjectionDetectionEngine $detector,
        private readonly ContentSanitizer $sanitizer,
        private readonly SecurityConfigProvider $config,
        private readonly TokenEstimator $tokenEstimator,
    ) {}

    public function wrapFeatureBrief(string $brief): string
    {
        $threshold = $this->getThreshold();

        // Scan and strip injection patterns
        $brief = $this->stripInjectionPatterns($brief, $threshold);

        // Enforce token budget
        $budget = (int) $this->config->get('context_budget_tokens');
        $brief = $this->enforceTokenBudget($brief, $budget);

        return '<user_context type="feature_brief" trust="contextual">'."\n"
            .$brief."\n"
            .'</user_context>';
    }

    public function wrapTechStack(string $content): string
    {
        return '<session_context type="tech_stack" trust="contextual">'."\n"
            .$content."\n"
            .'</session_context>';
    }

    public function wrapDiscoveryFindings(array $findings): string
    {
        $threshold = $this->getThreshold();
        $cleaned = [];

        foreach ($findings as $finding) {
            $finding = (string) $finding;

            if ($finding === '') {
                continue;
            }

            // Scan for injection
            $scan = $this->detector->scanWithThreshold($finding, $threshold);

            if ($scan->score >= $threshold && $scan->recommendedAction !== InjectionAction::Log) {
                // Strip this finding entirely — it's flagged
                continue;
            }

            // Truncate to 220 chars (existing pattern)
            if (mb_strlen($finding) > self::FINDING_MAX_LENGTH) {
                $finding = rtrim(mb_substr($finding, 0, self::FINDING_MAX_LENGTH)).'…';
            }

            $cleaned[] = $finding;
        }

        $content = implode("\n", array_map(fn (string $f) => '- '.$f, $cleaned));

        return '<session_context type="discovery_findings" trust="contextual">'."\n"
            .$content."\n"
            .'</session_context>';
    }

    public function validateUserPrompt(string $prompt): string
    {
        $threshold = $this->getThreshold();
        $scan = $this->detector->scanWithThreshold($prompt, $threshold);

        if ($scan->score < $threshold || $scan->recommendedAction === InjectionAction::Log) {
            return $prompt;
        }

        // Strip matched patterns from prompt
        $cleaned = $this->removeMatchedPatterns($prompt, $scan);

        // Never return empty — fall back to original if stripping leaves nothing
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return $prompt;
        }

        return $cleaned;
    }

    public function enforceTokenBudget(string $context, int $maxTokens): string
    {
        $estimate = $this->tokenEstimator->fastEstimate($context);

        if ($estimate <= $maxTokens) {
            return $context;
        }

        $result = $this->tokenEstimator->truncateToTokenBudget($context, $maxTokens);

        return $result['content'];
    }

    private function stripInjectionPatterns(string $content, float $threshold): string
    {
        $scan = $this->detector->scanWithThreshold($content, $threshold);

        if ($scan->score < $threshold || $scan->recommendedAction === InjectionAction::Log) {
            return $content;
        }

        return $this->removeMatchedPatterns($content, $scan);
    }

    private function removeMatchedPatterns(string $content, InjectionDetectionResult $scan): string
    {
        foreach ($scan->matchedPatterns as $match) {
            $pattern = $match['pattern'] ?? '';

            if ($pattern === '') {
                continue;
            }

            // Try case-insensitive literal replacement first
            $content = str_ireplace($pattern, '', $content);
        }

        // Clean up double spaces left by removals
        $content = preg_replace('/\s{2,}/', ' ', $content) ?? $content;

        return trim($content);
    }

    private function getThreshold(): float
    {
        return (float) ($this->config->get('injection_threshold_standard') ?? 0.5);
    }
}
